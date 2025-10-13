<?php
require_once __DIR__ . '/../config/database.php';

// Função para buscar ou criar um registro e retornar seu ID
function get_or_create_id($conn, $table, $conditions, $insert_data = []) {
    $where_clause = [];
    $params = [];
    $types = '';

    foreach ($conditions as $key => $data) {
        $where_clause[] = "`$key` = ?";
        $params[] = $data['value'];
        $types .= $data['type'];
    }
    
    $sql = "SELECT id FROM `$table` WHERE " . implode(' AND ', $where_clause);
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed on get: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    } else {
        $data_to_insert = !empty($insert_data) ? $insert_data : $conditions;
        $cols_sql = [];
        $vals_sql = [];
        $insert_params = [];
        $insert_types = '';

        foreach ($data_to_insert as $key => $data) {
            $cols_sql[] = "`$key`";
            $vals_sql[] = '?';
            $insert_params[] = $data['value'];
            $insert_types .= $data['type'];
        }

        $sql_insert = "INSERT INTO `$table` (" . implode(', ', $cols_sql) . ") VALUES (" . implode(', ', $vals_sql) . ")";
        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) die("Prepare failed on create: " . $conn->error);
        $stmt_insert->bind_param($insert_types, ...$insert_params);
        $stmt_insert->execute();
        return $conn->insert_id;
    }
}

if (empty($argv[1])) {
    die("ERRO: Forneça o caminho para o arquivo CSV como argumento.\n");
}

$csv_path = $argv[1];
if (!file_exists($csv_path)) {
    die("ERRO: Arquivo não encontrado: $csv_path\n");
}

$conn = connect_db();
$handle = fopen($csv_path, "r");

if ($handle !== FALSE) {
    $conn->begin_transaction();
    try {
        $current_turma_id = null;
        $is_curso_ativo = true;
        $ano_letivo = date('Y');
        $stats = ['alunos_add' => 0, 'alunos_update' => 0, 'alunos_skipped' => 0];

        echo "Iniciando importação de {$csv_path}...\n";

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            $data = array_map(function($cell) {
                return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1');
            }, $data);

            $is_header_row = false;
            foreach ($data as $cell) {
                if (strpos($cell, 'Curso:') !== false) {
                    $is_header_row = true;
                    break;
                }
            }

            if ($is_header_row) {
                $curso_nome = ''; $serie_nome = ''; $turma_nome = ''; $turno = '';
                foreach($data as $cell) {
                    if (preg_match('/Curso: (.*)/', $cell, $m)) $curso_nome = trim($m[1]);
                    if (preg_match('/Seriação: (.*)/', $cell, $m)) $serie_nome = trim($m[1]);
                    if (preg_match('/Turma: (.*)/', $cell, $m)) $turma_nome = trim($m[1]);
                    if (preg_match('/Turno: (.*)/', $cell, $m)) $turno = trim($m[1]);
                }

                if (!empty($curso_nome) && !empty($serie_nome) && !empty($turma_nome)) {
                    $current_curso_id = get_or_create_id($conn, 'cursos', ['nome' => ['value' => $curso_nome, 'type' => 's']]);
                    
                    // VERIFICA O STATUS DO CURSO
                    $status_stmt = $conn->prepare("SELECT status FROM cursos WHERE id = ?");
                    $status_stmt->bind_param("i", $current_curso_id);
                    $status_stmt->execute();
                    $status_result = $status_stmt->get_result()->fetch_assoc();
                    $status_stmt->close();

                    if ($status_result && $status_result['status'] === 'ATIVO') {
                        $is_curso_ativo = true;
                        $current_serie_id = get_or_create_id($conn, 'series', ['nome' => ['value' => $serie_nome, 'type' => 's'], 'curso_id' => ['value' => $current_curso_id, 'type' => 'i']]);
                        $current_turma_id = get_or_create_id($conn, 'turmas', 
                            ['nome' => ['value' => $turma_nome, 'type' => 's'], 'serie_id' => ['value' => $current_serie_id, 'type' => 'i'], 'ano_letivo' => ['value' => $ano_letivo, 'type' => 'i'], 'turno' => ['value' => $turno, 'type' => 's']]
                        );
                        echo "Processando Turma: $curso_nome -> $serie_nome -> $turma_nome ($turno)\n";
                    } else {
                        $is_curso_ativo = false;
                        $current_turma_id = null; // Explicitly set to null if course is archived
                        echo "PULANDO turmas do curso arquivado: $curso_nome\n";
                    }
                } else {
                    $is_curso_ativo = false; // Se não encontrou curso/serie/turma, não é ativo
                    $current_turma_id = null;
                }
                continue;
            }

            if (!$is_curso_ativo || $current_turma_id === null) {
                continue; // Pula as linhas de alunos se o curso da turma atual está arquivado ou turma_id não foi definida
            }

            $cgm = $data[3] ?? '';
            if (is_numeric($cgm) && strlen($cgm) > 5) {
                $nome_aluno = trim($data[4] ?? '');
                $situacao = trim($data[12] ?? 'Matriculado');

                if ($situacao === 'Matriculado') {
                    $stmt = $conn->prepare("SELECT id FROM estudantes WHERE cgm = ?");
                    $stmt->bind_param("s", $cgm);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        // Aluno existe. Apenas atualiza nome e situação, NÃO a turma_id.
                        $estudante_id = $result->fetch_assoc()['id'];
                        $stmt_update = $conn->prepare("UPDATE estudantes SET nome = ?, situacao = ? WHERE id = ?");
                        $stmt_update->bind_param("ssi", $nome_aluno, $situacao, $estudante_id);
                        $stmt_update->execute();
                        $stats['alunos_update']++;
                        echo "  [UPDATE] Aluno: $nome_aluno ($cgm) -> Turma ID: $current_turma_id (apenas nome/situacao)\n";
                    } else {
                        // Aluno não existe. Insere com a turma_id atual.
                        $stmt_insert = "INSERT INTO estudantes (cgm, nome, situacao, turma_id) VALUES (?, ?, ?, ?)";
                        $stmt_insert = $conn->prepare($stmt_insert);
                        $stmt_insert->bind_param("sssi", $cgm, $nome_aluno, $situacao, $current_turma_id);
                        $stmt_insert->execute();
                        $stats['alunos_add']++;
                        echo "  [INSERT] Aluno: $nome_aluno ($cgm) -> Turma ID: $current_turma_id\n";
                    }
                } else {
                    $stats['alunos_skipped']++;
                    echo "  [SKIP] Aluno: $nome_aluno ($cgm) -> Status: $situacao\n";
                }
            }
        }
        fclose($handle);
        $conn->commit();
        printf("Importação concluída! %d alunos adicionados, %d atualizados e %d ignorados (status não-matriculado).\n", $stats['alunos_add'], $stats['alunos_update'], $stats['alunos_skipped']);

    } catch (Exception $e) {
        $conn->rollback();
        echo 'Erro na transação: ' . $e->getMessage() . "\n";
    }
} else {
    echo "Não foi possível abrir o arquivo CSV.\n";
}

$conn->close();
?>