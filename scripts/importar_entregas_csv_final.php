<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

function normalize_str($str) {
    if (empty($str) || !is_string($str)) return '';
    $str = strtoupper(trim($str));
    $map = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N',
    ];
    $str = strtr($str, $map);
    return preg_replace('/[^A-Z0-9]/', '', $str);
}

$conn = connect_db();
echo "--- INICIANDO IMPORTAÇÃO (TESTE COM 6º E 7º ANOS) ---\n";

echo "Limpando registros de empréstimos de 2025 para as turmas de 6º e 7º ano...\n";
// Apaga apenas os empréstimos de alunos que pertencem a turmas de 6º e 7º ano
$delete_sql = "DELETE em FROM emprestimos em JOIN estudantes e ON em.estudante_id = e.id JOIN turmas t ON e.turma_id = t.id JOIN series s ON t.serie_id = s.id WHERE em.ano_letivo = 2025 AND (s.nome LIKE '6%' OR s.nome LIKE '7%')";
if ($conn->query($delete_sql)) {
    echo $conn->affected_rows . " registros antigos de 2025 (6º/7º) foram removidos.\n";
} else {
    die("ERRO ao limpar registros antigos: " . $conn->error . "\n");
}

echo "Carregando dados do banco para o cache...\n";
$materias_cache = []; // [nome_normalizado => id]
$materias_by_id_cache = []; // [id => nome]
$materias_result = $conn->query("SELECT id, nome FROM materias");
while ($r = $materias_result->fetch_assoc()) {
    $materias_cache[normalize_str($r['nome'])] = $r['id'];
    $materias_by_id_cache[$r['id']] = $r['nome'];
}

$livros_cache = [];
$livros_result = $conn->query("SELECT l.id, l.materia_id, l.serie_id FROM livros l");
while ($r = $livros_result->fetch_assoc()) {
    if (!isset($livros_cache[$r['serie_id']])) $livros_cache[$r['serie_id']] = [];
    $livros_cache[$r['serie_id']][$r['materia_id']] = $r['id'];
}

$estudantes_cache = [];
$estudantes_result = $conn->query("SELECT id, nome, turma_id FROM estudantes");
while ($r = $estudantes_result->fetch_assoc()) {
    $nome_normalizado = normalize_str($r['nome']);
    if (!isset($estudantes_cache[$nome_normalizado])) $estudantes_cache[$nome_normalizado] = [];
    $estudantes_cache[$nome_normalizado][] = ['id' => $r['id'], 'turma_id' => $r['turma_id']];
}

$turmas_cache = [];
$turmas_result = $conn->query("SELECT id, serie_id FROM turmas");
while ($r = $turmas_result->fetch_assoc()) { $turmas_cache[$r['id']] = $r['serie_id']; }

$series_by_id_cache = []; // [id => nome]
$series_result = $conn->query("SELECT id, nome FROM series");
while ($r = $series_result->fetch_assoc()) { $series_by_id_cache[$r['id']] = $r['nome']; }

echo "Cache concluído.\n";

$alias_map = [
    'INGLES' => 'LINGUAINGLESA',
    'PORTUGUES' => 'LINGUAPORTUGUESA'
];

$csv_files = glob(__DIR__ . '/../CONF*.[6-7]*.csv');
$total_emprestimos_criados = 0;
$alunos_pulados_nao_encontrados = [];
$alunos_pulados_duplicados = [];

$conn->begin_transaction();
try {
    $stmt_insert = $conn->prepare("INSERT INTO emprestimos (livro_id, estudante_id, ano_letivo, data_entrega, conservacao_entrega, status) VALUES (?, ?, ?, ?, ?, 'Emprestado')");

    foreach ($csv_files as $file) {
        echo "\nProcessando arquivo: " . basename($file) . "\n";
        $handle = fopen($file, "r");
        if (!$handle) continue;

        $header_row = fgetcsv($handle, 2000, ",");
        if(!$header_row) continue;

        $header_map = [];
        foreach ($header_row as $index => $col_name) {
            if ($index >= 3 && !empty(trim($col_name))) {
                $materia_normalizada = normalize_str($col_name);
                if (isset($alias_map[$materia_normalizada])) {
                    $materia_normalizada = $alias_map[$materia_normalizada];
                }
                if (isset($materias_cache[$materia_normalizada])) {
                    $header_map[$index] = ['id' => $materias_cache[$materia_normalizada], 'nome' => $materia_normalizada];
                }
            }
        }

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            if (empty($data) || empty($data[1])) continue;

            $nome_aluno = trim($data[1]);
            $nome_aluno_normalizado = normalize_str($nome_aluno);

            if (!isset($estudantes_cache[$nome_aluno_normalizado])) {
                $alunos_pulados_nao_encontrados[] = $nome_aluno;
                continue;
            }
            if (count($estudantes_cache[$nome_aluno_normalizado]) > 1) {
                $alunos_pulados_duplicados[] = $nome_aluno;
                continue;
            }

            $estudante = $estudantes_cache[$nome_aluno_normalizado][0];
            $estudante_id = $estudante['id'];
            $turma_id = $estudante['turma_id'];
            $serie_id = $turmas_cache[$turma_id] ?? null;

            if (!$serie_id) continue;

            foreach ($header_map as $col_index => $materia) {
                $conservacao = trim(strtoupper($data[$col_index] ?? ''));
                if (!empty($conservacao)) {
                    $materia_id = $materia['id'];
                    
                    if (!isset($livros_cache[$serie_id]) || !isset($livros_cache[$serie_id][$materia_id])) {
                        $materia_nome_legivel = $materias_by_id_cache[$materia_id] ?? 'ID Desconhecido';
                        $serie_nome_legivel = $series_by_id_cache[$serie_id] ?? 'ID Desconhecido';
                        echo "      - AVISO: Livro para a matéria '{$materia_nome_legivel}' e série '{$serie_nome_legivel}' não encontrado no cache. Pulando para o aluno '{$nome_aluno}'.\n";
                        continue;
                    }
                    
                    $livro_id = $livros_cache[$serie_id][$materia_id];
                    $data_entrega = '2025-02-10';
                    $ano_letivo = 2025;

                    $stmt_insert->bind_param("iisss", $livro_id, $estudante_id, $ano_letivo, $data_entrega, $conservacao);
                    $stmt_insert->execute();
                    $total_emprestimos_criados++;
                }
            }
        }
        fclose($handle);
    }

    $stmt_insert->close();
    $conn->commit();
    echo "\n--- IMPORTAÇÃO CONCLUÍDA ---\n";
    echo "Total de {$total_emprestimos_criados} registros de empréstimo criados com sucesso.\n";

    if (!empty($alunos_pulados_nao_encontrados)) {
        echo "\nAVISO: Os seguintes alunos não foram encontrados no banco de dados e foram pulados:\n";
        foreach(array_unique($alunos_pulados_nao_encontrados) as $nome) echo " - {$nome}\n";
    }
    if (!empty($alunos_pulados_duplicados)) {
        echo "\nAVISO: Os seguintes alunos existem mais de uma vez no banco (homônimos) e foram pulados para segurança:\n";
        foreach(array_unique($alunos_pulados_duplicados) as $nome) echo " - {$nome}\n";
    }

} catch (Exception $e) {
    $conn->rollback();
    die("\nERRO CRÍTICO DURANTE A TRANSAÇÃO: " . $e->getMessage() . ". Nenhuma alteração foi salva.\n");
}

$conn->close();
?>