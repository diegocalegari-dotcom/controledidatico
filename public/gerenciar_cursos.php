<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

$mensagem = '';
$view = isset($_GET['view']) && $_GET['view'] == 'archived' ? 'archived' : 'active';

// Lógica de Ações
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lógica de Ações em Massa
    if (isset($_POST['bulk_action']) && isset($_POST['selected_cursos'])) {
        $action = $_POST['bulk_action'];
        $selected_ids = $_POST['selected_cursos'];
        $success_messages = [];
        $error_messages = [];

        if ($action == 'archive_selected') {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $stmt = $conn->prepare("UPDATE cursos SET status = 'ARQUIVADO' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) $success_messages[] = 'Cursos arquivados com sucesso!';
            else $error_messages[] = 'Erro ao arquivar cursos.';
            if (isset($stmt)) $stmt->close();
        } elseif ($action == 'restore_selected') {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $stmt = $conn->prepare("UPDATE cursos SET status = 'ATIVO' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) $success_messages[] = 'Cursos restaurados com sucesso!';
            else $error_messages[] = 'Erro ao restaurar cursos.';
            if (isset($stmt)) $stmt->close();
        } elseif ($action == 'delete_selected') {
            foreach ($selected_ids as $id) {
                $id = (int)$id;
                // Check for active series associated with this course
                $check_active_series_stmt = $conn->prepare("SELECT COUNT(*) FROM series WHERE curso_id = ? AND status = 'ATIVO'");
                $check_active_series_stmt->bind_param("i", $id);
                $check_active_series_stmt->execute();
                $check_active_series_stmt->bind_result($active_series_count);
                $check_active_series_stmt->fetch();
                $check_active_series_stmt->close();

                if ($active_series_count > 0) {
                    $error_messages[] = "Não é possível excluir o curso ID {$id}, pois ele ainda possui séries ATIVAS associadas. Arquive-as primeiro.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM cursos WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    try {
                        if ($stmt->execute()) {
                            $success_messages[] = "Curso ID {$id} excluído permanentemente!";
                        } else {
                            $error_messages[] = "Erro ao excluir curso ID {$id}: " . $stmt->error;
                        }
                    } catch (mysqli_sql_exception $e) {
                        if ($e->getCode() == 1451) {
                            $error_messages[] = "Não é possível excluir o curso ID {$id}. Existem séries (arquivadas ou ativas) ainda associadas a ele. Por favor, exclua todas as séries associadas primeiro.";
                        } else {
                            $error_messages[] = "Erro ao excluir curso ID {$id}: " . $e->getMessage();
                        }
                    }
                    if (isset($stmt)) $stmt->close();
                }
            }
        }
        if (!empty($success_messages)) {
            $mensagem .= '<div class="alert alert-success">' . implode('<br>', $success_messages) . '</div>';
        }
        if (!empty($error_messages)) {
            $mensagem .= '<div class="alert alert-danger">' . implode('<br>', $error_messages) . '</div>';
        }
    }
    // Arquivar
    if (isset($_POST['archive_id'])) {
        $id = (int)$_POST['archive_id'];
        $stmt = $conn->prepare("UPDATE cursos SET status = 'ARQUIVADO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Curso arquivado com sucesso!</div>';
        $stmt->close();
    }
    // Restaurar
    elseif (isset($_POST['restore_id'])) {
        $id = (int)$_POST['restore_id'];
        $stmt = $conn->prepare("UPDATE cursos SET status = 'ATIVO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Curso restaurado com sucesso!</div>';
        $stmt->close();
    }
    // Excluir Permanentemente
    elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];

        // Check for active series associated with this course
        $check_active_series_stmt = $conn->prepare("SELECT COUNT(*) FROM series WHERE curso_id = ? AND status = 'ATIVO'");
        $check_active_series_stmt->bind_param("i", $id);
        $check_active_series_stmt->execute();
        $check_active_series_stmt->bind_result($active_series_count);
        $check_active_series_stmt->fetch();
        $check_active_series_stmt->close();

        if ($active_series_count > 0) {
            $mensagem = '<div class="alert alert-danger">Não é possível excluir este curso, pois ele ainda possui séries ATIVAS associadas. Arquive-as primeiro.</div>';
        } else {
            // If no active series, attempt to delete the course
            $stmt = $conn->prepare("DELETE FROM cursos WHERE id = ?");
            $stmt->bind_param("i", $id);
            try {
                if ($stmt->execute()) {
                    $mensagem = '<div class="alert alert-success">Curso excluído permanentemente!</div>';
                } else {
                    // This catch block might not be reached if the execute() fails without an exception
                    $mensagem = '<div class="alert alert-danger">Erro ao excluir curso: ' . $stmt->error . '</div>';
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1451) {
                    // This means there are still archived series preventing deletion
                    $mensagem = '<div class="alert alert-danger">Não é possível excluir este curso. Existem séries (arquivadas ou ativas) ainda associadas a ele. Por favor, exclua todas as séries associadas primeiro.</div>';
                } else {
                    $mensagem = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
                }
            }
            $stmt->close();
        }
    }
    // Adicionar Novo
    elseif (isset($_POST['nome_curso'])) {
        $nome = $conn->real_escape_string(trim($_POST['nome_curso']));
        if (!empty($nome)) {
            $stmt = $conn->prepare("INSERT INTO cursos (nome) VALUES (?)");
            $stmt->bind_param("s", $nome);
            if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Curso adicionado com sucesso!</div>';
            $stmt->close();
        }
    }
}

// Query principal
$status_filter = ($view == 'active') ? 'ATIVO' : 'ARQUIVADO';
$cursos = $conn->query("SELECT * FROM cursos WHERE status = '$status_filter' ORDER BY nome");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Cursos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gerenciar Cursos (<?php echo $view == 'active' ? 'Ativos' : 'Arquivados'; ?>)</h2>
            <div>
                <?php if ($view == 'active'): ?>
                    <a href="?view=archived" class="btn btn-secondary">Ver Arquivados</a>
                <?php else: ?>
                    <a href="?view=active" class="btn btn-secondary">Ver Ativos</a>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $mensagem; ?>

        <?php if ($view == 'active'): ?>
        <div class="card mb-4">
            <div class="card-header">Adicionar Novo Curso</div>
            <div class="card-body">
                <form action="gerenciar_cursos.php" method="POST">
                    <div class="input-group">
                        <input type="text" class="form-control" name="nome_curso" placeholder="Nome do Curso" required>
                        <button type="submit" class="btn btn-primary">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Cursos <?php echo $view == 'active' ? 'Ativos' : 'Arquivados'; ?></div>
            <div class="card-body">
                <form action="" method="POST" id="bulk_action_form">
                    <div class="mb-3 d-flex align-items-center">
                        <select name="bulk_action" class="form-select me-2" style="width: auto;">
                            <option value="">Ações em massa</option>
                            <option value="archive_selected">Arquivar Selecionados</option>
                            <option value="restore_selected">Restaurar Selecionados</option>
                            <?php if ($view == 'archived'): ?>
                            <option value="delete_selected">Excluir Selecionados Permanentemente</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="btn btn-info" onclick="return confirmBulkAction(this.form);">Aplicar</button>
                    </div>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select_all_cursos"></th>
                                <th>Nome</th>
                                <th style="width: 220px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $cursos->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_cursos[]" value="<?php echo $row['id']; ?>"></td>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td>
                                    <?php if ($view == 'active'): ?>
                                        <a href="editar_curso.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <form action="" method="POST" style="display: inline;">
                                            <input type="hidden" name="archive_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">Arquivar</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="submitRestoreForm(<?php echo $row['id']; ?>)">Restaurar</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="submitDeleteForm(<?php echo $row['id']; ?>)">Excluir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>
<script>
    document.getElementById('select_all_cursos').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_cursos[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    function confirmBulkAction(form) {
        const selectElement = form.elements['bulk_action'];
        if (selectElement.value === 'delete_selected') {
            return confirm('ATENÇÃO! Você está prestes a EXCLUIR PERMANENTEMENTE os cursos selecionados. Esta ação não pode ser desfeita. Deseja continuar?');
        }
        return confirm('Confirmar ação em massa?');
    }

    function submitRestoreForm(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?view=archived';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'restore_id';
        input.value = id;
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    }

    function submitDeleteForm(id) {
        if (confirm('ATENÇÃO! Isso excluirá o curso permanentemente. Deseja continuar?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?view=archived';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_id';
            input.value = id;
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
</body>
</html>
<?php $conn->close(); ?>