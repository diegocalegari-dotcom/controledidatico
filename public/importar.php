<?php
require_once '../config/database.php';

$mensagem = '';

/**
 * Busca ou cria um registro em uma tabela e retorna seu ID.
 * @param mysqli $conn Conexão com o banco.
 * @param string $table Tabela para consultar.
 * @param array $conditions Colunas e valores para a cláusula WHERE. Ex: ['nome' => ['value' => 'Curso A', 'type' => 's']]
 * @param array $insert_data Colunas e valores para inserir caso não encontre. Se vazio, usa $conditions.
 * @return int ID do registro.
 */
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['arquivo_csv'])) {
    $conn = connect_db();
    $file = $_FILES['arquivo_csv'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $csv_path = $file['tmp_name'];
        $handle = fopen($csv_path, "r");

        if ($handle !== FALSE) {
            $conn->begin_transaction();
            try {
                $current_curso_id = null;
                $current_serie_id = null;
                $ano_letivo = date('Y');
                $stats = ['alunos_add' => 0, 'alunos_update' => 0];

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Converte cada célula para UTF-8
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
                        $curso_nome = '';
                        $serie_nome = '';
                        $turma_nome = '';
                        $turno = '';

                        foreach($data as $cell) {
                            if (preg_match('/Curso: (.*)/', $cell, $curso_matches)) $curso_nome = trim($curso_matches[1]);
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
                        continue; // Pula para a próxima linha após processar o cabeçalho
                    }

                    $cgm = $data[3] ?? '';
                    if (is_numeric($cgm) && strlen($cgm) > 5) {
                        $nome_aluno = trim($data[4] ?? '');
                        $situacao = trim($data[12] ?? 'Matriculado');

                        $stmt = $conn->prepare("SELECT id FROM estudantes WHERE cgm = ?");
                        $stmt->bind_param("s", $cgm);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $estudante_id = $row['id'];
                            $stmt_update = $conn->prepare("UPDATE estudantes SET nome = ?, situacao = ?, turma_id = ? WHERE id = ?");
                            $stmt_update->bind_param("ssii", $nome_aluno, $situacao, $current_turma_id, $estudante_id);
                            $stmt_update->execute();
                            $stats['alunos_update']++;
                        } else {
                            $stmt_insert = $conn->prepare("INSERT INTO estudantes (cgm, nome, situacao, turma_id) VALUES (?, ?, ?, ?)");
                            $stmt_insert->bind_param("sssi", $cgm, $nome_aluno, $situacao, $current_turma_id);
                            $stmt_insert->execute();
                            $stats['alunos_add']++;
                        }
                    }
                }
                fclose($handle);
                $conn->commit();
                $mensagem = sprintf('<div class="alert alert-success">Importação concluída! %d alunos adicionados e %d alunos atualizados.</div>', $stats['alunos_add'], $stats['alunos_update']);

            } catch (Exception $e) {
                $conn->rollback();
                $mensagem = '<div class="alert alert-danger">Erro na transação: ' . $e->getMessage() . '</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-danger">Não foi possível abrir o arquivo CSV.</div>';
        }
    } else {
        $mensagem = '<div class="alert alert-danger">Erro no upload do arquivo.</div>';
    }
    $conn->close();
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .alert .alert-heading i, .alert .step-heading i {
            font-size: 1.5rem; /* Aumenta o tamanho do ícone */
            margin-right: 10px; /* Adiciona espaço à direita */
            vertical-align: middle; /* Alinha o ícone com o texto */
        }
        .alert .step-heading {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_materias.php">Matérias</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_cursos.php">Cursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_series.php">Séries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gerenciar_livros.php">Livros</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="importar.php">Importar Alunos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracoes.php">Configurações</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Caixa de Instruções -->
        <div class="alert alert-info" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Como Importar Corretamente</h4>
            <p>Siga os passos abaixo para garantir uma importação de dados bem-sucedida.</p>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h5 class="step-heading"><i class="bi bi-1-circle"></i> Passo 1: Importação Inicial</h5>
                    <p>Primeiro, importe a relação de alunos matriculados por turma (manhã e/ou tarde). O sistema irá adicionar os novos cursos, séries e turmas automaticamente.</p>
                    <ol>
                        <li>Exporte a relação de alunos do sistema de gestão acadêmica em formato <strong>.xls</strong>.</li>
                        <li>Abra o arquivo e salve-o como <strong>CSV (separado por vírgulas)</strong>.</li>
                        <li>Use o formulário abaixo para importar este arquivo <strong>.csv</strong>.</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h5 class="step-heading"><i class="bi bi-2-circle"></i> Passo 2: Limpeza e Importação Final</h5>
                    <p>Após a primeira importação, alguns cursos e séries que não utilizam livros didáticos (como CELEM, PMA, etc.) podem ter sido criados. É importante arquivá-los.</p>
                    <ol>
                        <li>Vá para <a href="gerenciar_cursos.php" class="alert-link">Gerenciar Cursos</a> e <a href="gerenciar_series.php" class="alert-link">Gerenciar Séries</a> para arquivar os itens que não são relevantes.</li>
                        <li><strong>Importe o mesmo arquivo .csv novamente.</strong></li>
                        <li>Isso irá atualizar o cadastro dos estudantes, garantindo que eles sejam associados apenas às turmas e cursos corretos que utilizam o livro didático.</li>
                    </ol>
                </div>
            </div>
            <hr>
            <p class="mb-0"><strong>Atenção:</strong> Este processo de duas etapas é crucial para evitar que estudantes de cursos complementares apareçam nas listas de entrega de livros.</p>
        </div>
        <!-- Fim da Caixa de Instruções -->

        <h2>Importar Alunos, Cursos, Séries e Turmas</h2>
        <p>Selecione o arquivo CSV de matrículas (como manha.csv ou tarde.csv) para popular o banco de dados automaticamente.</p>
        
        <?php echo $mensagem; ?>

        <div class="card">
            <div class="card-body">
                <form action="importar.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="arquivo_csv" class="form-label">Arquivo .csv</label>
                        <input class="form-control" type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Importar</button>
                </form>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
