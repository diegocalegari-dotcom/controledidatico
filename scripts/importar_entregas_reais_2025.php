<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

function normalize_str($str) {
    $str = strtoupper(trim($str));
    $str = preg_replace('/[\x{00C0}-\x{00C5}]/u', 'A', $str);
    $str = preg_replace('/[\x{00C8}-\x{00CB}]/u', 'E', $str);
    $str = preg_replace('/[\x{00CC}-\x{00CF}]/u', 'I', $str);
    $str = preg_replace('/[\x{00D2}-\x{00D6}]/u', 'O', $str);
    $str = preg_replace('/[\x{00D9}-\x{00DC}]/u', 'U', $str);
    $str = preg_replace('/\x{00C7}/u', 'C', $str);
    $str = preg_replace('/[^A-Z0-9]/', '', $str);
    return $str;
}

$conn = connect_db();
echo "Iniciando importação de entregas reais de 2025...\n";

echo "Limpando registros de empréstimos de 2025 gerados anteriormente...\n";
$delete_sql = "DELETE FROM emprestimos WHERE ano_letivo = 2025";
if ($conn->query($delete_sql)) {
    echo $conn->affected_rows . " registros antigos de 2025 foram removidos.\n";
} else {
    die("ERRO ao limpar registros antigos: " . $conn->error . "\n");
}

echo "Carregando dados do banco para o cache...\n";
$materias_cache = [];
$materias_result = $conn->query("SELECT id, nome FROM materias");
while ($row = $materias_result->fetch_assoc()) {
    $materias_cache[normalize_str($row['nome'])] = $row['id'];
}

$livros_cache = [];
$livros_result = $conn->query("SELECT l.id, l.materia_id, l.serie_id FROM livros l JOIN series s ON l.serie_id = s.id");
while ($row = $livros_result->fetch_assoc()) {
    if (!isset($livros_cache[$row['serie_id']])) $livros_cache[$row['serie_id']] = [];
    $livros_cache[$row['serie_id']][$row['materia_id']] = $row['id'];
}

$estudantes_cache = [];
$estudantes_result = $conn->query("SELECT id, cgm, turma_id FROM estudantes");
while ($row = $estudantes_result->fetch_assoc()) {
    if(!empty($row['cgm'])) $estudantes_cache[$row['cgm']] = ['id' => $row['id'], 'turma_id' => $row['turma_id']];
}

$turmas_cache = [];
$turmas_result = $conn->query("SELECT id, serie_id FROM turmas");
while ($row = $turmas_result->fetch_assoc()) {
    $turmas_cache[$row['id']] = $row['serie_id'];
}
echo "Cache concluído.\n";

$csv_files = glob(__DIR__ . '/../CONF*.csv');
$total_emprestimos_criados = 0;

$conn->begin_transaction();
try {
    $stmt_insert = $conn->prepare(
        "INSERT INTO emprestimos (livro_id, estudante_id, ano_letivo, data_entrega, conservacao_entrega, status) VALUES (?, ?, ?, ?, 'BOM', 'Emprestado')"
    );

    foreach ($csv_files as $file) {
        echo "\nProcessando arquivo: " . basename($file) . "\n";
        $handle = fopen($file, "r");
        if ($handle === FALSE) {
            echo "AVISO: Não foi possível abrir o arquivo ".basename($file)."\n";
            continue;
        }

        $header_map = [];
        $header_found = false;

        while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
            if (empty($data) || empty($data[0])) continue;

            $data = array_map(function($cell) { 
                return mb_check_encoding($cell, 'UTF-8') ? $cell : mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1');
            }, $data);

            // Lógica de detecção de cabeçalho
            if (!$header_found && isset($data[1]) && strpos(strtoupper($data[1]), 'NOME DO ALUNO') !== false) {
                foreach ($data as $index => $col_name) {
                    if ($index > 3 && !empty(trim($col_name))) {
                        $header_map[$index] = normalize_str($col_name);
                    }
                }
                $header_found = true;
                echo "Cabeçalho de matérias mapeado: " . implode(', ', $header_map) . "\n";
                continue;
            }

            if (!$header_found) continue;

            $cgm = trim($data[2] ?? '');
            $nome_aluno = trim($data[1] ?? '');

            if (empty($cgm)) {
                if(!empty($nome_aluno)) echo "AVISO: Aluno '{$nome_aluno}' pulado (CGM vazio).\n";
                continue;
            }

            if (!isset($estudantes_cache[$cgm])) {
                echo "AVISO: Aluno '{$nome_aluno}' (CGM: {$cgm}) não encontrado no banco de dados. Pulando.\n";
                continue;
            }

            $estudante_id = $estudantes_cache[$cgm]['id'];
            $turma_id = $estudantes_cache[$cgm]['turma_id'];
            $serie_id = $turmas_cache[$turma_id] ?? null;

            if (!$serie_id) {
                echo "AVISO: Série para o aluno '{$nome_aluno}' (CGM: {$cgm}) não encontrada. Pulando.\n";
                continue;
            }

            foreach ($header_map as $col_index => $materia_normalizada) {
                if (!isset($data[$col_index])) continue;
                $status_entrega = trim(strtoupper($data[$col_index] ?? ''));
                
                if ($status_entrega === 'OK') {
                    if (!isset($materias_cache[$materia_normalizada])) {
                        echo "AVISO: Matéria '{$materia_normalizada}' do CSV não encontrada no cache. Pulando livro para o aluno {$nome_aluno}.\n";
                        continue;
                    }
                    $materia_id = $materias_cache[$materia_normalizada];

                    if (!isset($livros_cache[$serie_id][$materia_id])) {
                        echo "AVISO: Livro para a matéria '{$materia_normalizada}' e série ID {$serie_id} não encontrado no cache. Pulando para o aluno {$nome_aluno}.\n";
                        continue;
                    }
                    $livro_id = $livros_cache[$serie_id][$materia_id];
                    
                    $data_entrega = '2025-02-10';
                    $ano_letivo = 2025;

                    $stmt_insert->bind_param("iisi", $livro_id, $estudante_id, $ano_letivo, $data_entrega);
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

} catch (Exception $e) {
    $conn->rollback();
    die("\nERRO CRÍTICO DURANTE A TRANSAÇÃO: " . $e->getMessage() . ". Nenhuma alteração foi salva.\n");
}

$conn->close();
?>