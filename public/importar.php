<?php
require_once '../config/database.php';
require_once 'components/navbar.php';

$mensagem = '';
$step = 1;
$courses_found = [];
$temp_file_path = '';

// Função get_or_create_id permanece a mesma...
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
        $stmt_insert->bind_param($insert_types, ...$insert_params);
        $stmt_insert->execute();
        return $conn->insert_id;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // STEP 1: Análise do arquivo CSV
    if (isset($_FILES['arquivo_csv'])) {
        $file = $_FILES['arquivo_csv'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $temp_dir = sys_get_temp_dir();
            $temp_file_name = uniqid('import_', true) . '.csv';
            $temp_file_path = $temp_dir . DIRECTORY_SEPARATOR . $temp_file_name;

            if (move_uploaded_file($file['tmp_name'], $temp_file_path)) {
                $handle = fopen($temp_file_path, "r");
                if ($handle !== FALSE) {
                    $courses = [];
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $data = array_map(function($cell) {
                            return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1');
                        }, $data);

                        foreach ($data as $cell) {
                            if (preg_match('/Curso: (.*)/', $cell, $matches)) {
                                $course_name = trim($matches[1]);
                                if (!in_array($course_name, $courses)) {
                                    $courses[] = $course_name;
                                }
                            }
                        }
                    }
                    fclose($handle);
                    $courses_found = $courses;
                    $step = 2;
                } else {
                    $mensagem = '<div class="alert alert-danger">Não foi possível abrir o arquivo temporário.</div>';
                }
            } else {
                $mensagem = '<div class="alert alert-danger">Erro ao mover o arquivo para o diretório temporário.</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-danger">Erro no upload do arquivo.</div>';
        }
    }
    // STEP 2: Importação dos cursos selecionados
    elseif (isset($_POST['courses_to_import']) && isset($_POST['temp_file'])) {
        $conn = connect_db();
        $courses_to_import = $_POST['courses_to_import'];
        $temp_file_path = $_POST['temp_file'];

        if (file_exists($temp_file_path)) {
            $handle = fopen($temp_file_path, "r");
            if ($handle !== FALSE) {
                $conn->begin_transaction();
                try {
                    $current_curso_id = null;
                    $current_serie_id = null;
                    $ano_letivo_q = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo' LIMIT 1");
                    $ano_letivo = $ano_letivo_q->fetch_assoc()['valor'] ?? date('Y');
                    $stats = ['alunos_add' => 0, 'alunos_update' => 0];
                    $should_import_section = false;

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $data = array_map(function($cell) {
                            return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1');
                        }, $data);

                        $is_header_row = false;
                        $curso_nome = '';
                        foreach ($data as $cell) {
                            if (preg_match('/Curso: (.*)/', $cell, $curso_matches)) {
                                $is_header_row = true;
                                $curso_nome = trim($curso_matches[1]);
                                break;
                            }
                        }

                        if ($is_header_row) {
                            if (in_array($curso_nome, $courses_to_import)) {
                                $should_import_section = true;
                                $serie_nome = '';
                                $turma_nome = '';
                                $turno = '';

                                foreach($data as $cell) {
                                    if (preg_match('/Seriação: (.*)/', $cell, $serie_matches)) $serie_nome = trim($serie_matches[1]);
                                    if (preg_match('/Turma: (.*)/', $cell, $turma_matches)) $turma_nome = trim($turma_matches[1]);
                                    if (preg_match('/Turno: (.*)/', $cell, $turno_matches)) $turno = trim($turno_matches[1]);
                                }

                                if (!empty($curso_nome) && !empty($serie_nome) && !empty($turma_nome)) {
                                    $current_curso_id = get_or_create_id($conn, 'cursos', ['nome' => ['value' => $curso_nome, 'type' => 's']]);
                                    $current_serie_id = get_or_create_id($conn, 'series', ['nome' => ['value' => $serie_nome, 'type' => 's'], 'curso_id' => ['value' => $current_curso_id, 'type' => 'i']]);
                                    $current_turma_id = get_or_create_id($conn, 'turmas', 
                                        ['nome' => ['value' => $turma_nome, 'type' => 's'], 'serie_id' => ['value' => $current_serie_id, 'type' => 'i'], 'ano_letivo' => ['value' => $ano_letivo, 'type' => 'i'], 'turno' => ['value' => $turno, 'type' => 's']]
                                    );
                                }
                            } else {
                                $should_import_section = false;
                            }
                            continue;
                        }

                        if ($should_import_section) {
                            $cgm = $data[3] ?? '';
                                if (is_numeric($cgm) && strlen($cgm) > 5) {
                                    $nome_aluno = trim($data[4] ?? '');
                                    $situacao = trim($data[12] ?? 'Matriculado');

                                    // Lógica de Duplicação Controlada
                                    $new_cgm_for_year = $ano_letivo . '-' . $cgm;

                                    $stmt_check_new = $conn->prepare("SELECT id FROM estudantes WHERE cgm = ?");
                                    $stmt_check_new->bind_param("s", $new_cgm_for_year);
                                    $stmt_check_new->execute();
                                    $result_new = $stmt_check_new->get_result();

                                    if ($result_new->num_rows > 0) {
                                        // O aluno já foi importado para este ano, apenas atualiza
                                        $row = $result_new->fetch_assoc();
                                        $estudante_id = $row['id'];
                                        $stmt_update = $conn->prepare("UPDATE estudantes SET nome = ?, situacao = ?, turma_id = ? WHERE id = ?");
                                        $stmt_update->bind_param("ssii", $nome_aluno, $situacao, $current_turma_id, $estudante_id);
                                        $stmt_update->execute();
                                        $stats['alunos_update']++;
                                    } else {
                                        // Cria um novo registro para o aluno no novo ano letivo
                                        $stmt_insert = $conn->prepare("INSERT INTO estudantes (cgm, nome, situacao, turma_id) VALUES (?, ?, ?, ?)");
                                        $stmt_insert->bind_param("sssi", $new_cgm_for_year, $nome_aluno, $situacao, $current_turma_id);
                                        $stmt_insert->execute();
                                        $stats['alunos_add']++;
                                    }
                                }
                        }
                    }
                    fclose($handle);
                    $conn->commit();
                    $mensagem = sprintf('<div class="alert alert-success">Importação concluída! %d alunos adicionados e %d alunos atualizados.</div>', $stats['alunos_add'], $stats['alunos_update']);
                    unlink($temp_file_path); // Apaga o arquivo temporário

                } catch (Exception $e) {
                    $conn->rollback();
                    $mensagem = '<div class="alert alert-danger">Erro na transação: ' . $e->getMessage() . '</div>';
                }
                $conn->close();
            } else {
                 $mensagem = '<div class="alert alert-danger">Arquivo temporário não encontrado ou ilegível.</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-danger">Arquivo temporário expirou ou não foi encontrado. Por favor, envie o arquivo novamente.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Alunos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div class="container mt-4">
                <div class="alert alert-info" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Como Funciona a Importação</h4>
            <p>A importação agora é feita em duas etapas para garantir que apenas os dados relevantes sejam adicionados ao sistema.</p>
            <hr>
            <p><strong>Passo 1: Análise do Arquivo</strong><br>
            Envie o arquivo CSV exportado do sistema de gestão acadêmica. O sistema irá ler o arquivo e identificar todos os cursos contidos nele, sem salvar nenhuma informação no banco de dados.</p>
            <p><strong>Passo 2: Seleção e Importação</strong><br>
            Na tela seguinte, você verá uma lista de todos os cursos encontrados. Desmarque os cursos que não utilizam livros didáticos (como CELEM, PMA, etc.). Ao confirmar, apenas os alunos, turmas e séries dos cursos selecionados serão importados ou atualizados no sistema.</p>
            <hr>
            <p class="mb-0">Este novo processo substitui a necessidade de importar o mesmo arquivo duas vezes e garante um controle muito maior sobre os dados.</p>
        </div>

        <h2 class="mt-4">Iniciar Nova Importação</h2>
        
        <?php echo $mensagem; ?>

        <?php if ($step == 1): ?>
            <div class="card">
                <div class="card-header"><strong>Passo 1: Enviar e Analisar Arquivo</strong></div>
                <div class="card-body">
                    <form action="importar.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="arquivo_csv" class="form-label">Selecione o arquivo CSV de matrículas (ex: manha.csv)</label>
                            <input class="form-control" type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Analisar Arquivo</button>
                    </form>
                </div>
            </div>
        <?php elseif ($step == 2 && !empty($courses_found)): ?>
            <div class="card">
                <div class="card-header"><strong>Passo 2: Selecionar Cursos para Importar</strong></div>
                <div class="card-body">
                    <form action="importar.php" method="POST">
                        <input type="hidden" name="temp_file" value="<?php echo htmlspecialchars($temp_file_path); ?>">
                        <p>Selecione os cursos que você deseja importar. As séries, turmas e alunos associados a esses cursos serão criados ou atualizados.</p>
                        <div class="mb-3">
                            <?php foreach ($courses_found as $course): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="courses_to_import[]" value="<?php echo htmlspecialchars($course); ?>" id="course_<?php echo md5($course); ?>" checked>
                                    <label class="form-check-label" for="course_<?php echo md5($course); ?>">
                                        <?php echo htmlspecialchars($course); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-success">Importar Cursos Selecionados</button>
                        <a href="importar.php" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>