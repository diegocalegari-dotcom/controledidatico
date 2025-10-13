<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

$mensagem = '';
$view = isset($_GET['view']) && $_GET['view'] == 'archived' ? 'archived' : 'active';

// Lógica de Ações
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lógica de Ações em Massa
    if (isset($_POST['bulk_action']) && isset($_POST['selected_series'])) {
        $action = $_POST['bulk_action'];
        $selected_ids = $_POST['selected_series'];
        $success_messages = [];
        $error_messages = [];

        if ($action == 'archive_selected') {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $stmt = $conn->prepare("UPDATE series SET status = 'ARQUIVADO' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) $success_messages[] = 'Séries arquivadas com sucesso!';
            else $error_messages[] = 'Erro ao arquivar séries.';
            if (isset($stmt)) $stmt->close();
        } elseif ($action == 'restore_selected') {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $stmt = $conn->prepare("UPDATE series SET status = 'ATIVO' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) $success_messages[] = 'Séries restauradas com sucesso!';
            else $error_messages[] = 'Erro ao restaurar séries.';
            if (isset($stmt)) $stmt->close();
        } elseif ($action == 'delete_selected') {
            foreach ($selected_ids as $id) {
                $id = (int)$id;

                // Check for active livros associated with this series
                $check_active_livros_stmt = $conn->prepare("SELECT COUNT(*) FROM livros WHERE serie_id = ? AND status = 'ATIVO'");
                $check_active_livros_stmt->bind_param("i", $id);
                $check_active_livros_stmt->execute();
                $check_active_livros_stmt->bind_result($active_livros_count);
                $check_active_livros_stmt->fetch();
                $check_active_livros_stmt->close();

                if ($active_livros_count > 0) {
                    $error_messages[] = "Não é possível excluir a série ID {$id}, pois ela ainda possui livros ATIVOS associados. Arquive-os primeiro.";
                } else {
                    // Get all turmas for the current serie
                    $turmas_to_delete_stmt = $conn->prepare("SELECT id FROM turmas WHERE serie_id = ?");
                    $turmas_to_delete_stmt->bind_param("i", $id);
                    $turmas_to_delete_stmt->execute();
                    $turmas_result = $turmas_to_delete_stmt->get_result();
                    $turmas_ids = [];
                    while ($row = $turmas_result->fetch_assoc()) {
                        $turmas_ids[] = $row['id'];
                    }
                    $turmas_to_delete_stmt->close();

                    if (!empty($turmas_ids)) {
                        // For each turma, set estudante.turma_id to NULL
                        $placeholders = implode(',', array_fill(0, count($turmas_ids), '?'));
                        $types = str_repeat('i', count($turmas_ids));
                        $update_estudantes_stmt = $conn->prepare("UPDATE estudantes SET turma_id = NULL WHERE turma_id IN ($placeholders)");
                        $update_estudantes_stmt->bind_param($types, ...$turmas_ids);
                        if (!$update_estudantes_stmt->execute()) {
                            $error_messages[] = "Erro ao desassociar estudantes das turmas da série ID {$id}: " . $update_estudantes_stmt->error;
                            goto end_series_delete_loop;
                        }
                        $update_estudantes_stmt->close();

                        // Now, delete the turmas
                        $delete_turmas_stmt = $conn->prepare("DELETE FROM turmas WHERE serie_id = ?");
                        $delete_turmas_stmt->bind_param("i", $id);
                        if (!$delete_turmas_stmt->execute()) {
                            $error_messages[] = "Erro ao excluir turmas associadas à série ID {$id}: " . $delete_turmas_stmt->error;
                            goto end_series_delete_loop;
                        }
                        if ($delete_turmas_stmt->affected_rows > 0) {
                            $success_messages[] = "Turmas associadas à série ID {$id} foram excluídas.";
                        }
                        $delete_turmas_stmt->close();
                    }

                    // Now, delete all associated livros (active or archived)
                    $delete_livros_stmt = $conn->prepare("DELETE FROM livros WHERE serie_id = ?");
                    $delete_livros_stmt->bind_param("i", $id);
                    if (!$delete_livros_stmt->execute()) {
                        $error_messages[] = "Erro ao excluir livros associados à série ID {$id}: " . $delete_livros_stmt->error;
                        goto end_series_delete_loop;
                    }
                    if ($delete_livros_stmt->affected_rows > 0) {
                        $success_messages[] = "Livros associados à série ID {$id} foram excluídos.";
                    }
                    $delete_livros_stmt->close();

                    // Now, delete the series
                    $stmt = $conn->prepare("DELETE FROM series WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    try {
                        if ($stmt->execute()) {
                            $success_messages[] = "Série ID {$id} excluída permanentemente!";
                        } else {
                            $error_messages[] = "Erro ao excluir série ID {$id}: " . $stmt->error;
                        }
                    } catch (mysqli_sql_exception $e) {
                        $error_messages[] = "Erro inesperado ao excluir série ID {$id}: " . $e->getMessage();
                    }
                    if (isset($stmt)) $stmt->close();
                }
            end_series_delete_loop:; // Label for goto
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
        $stmt = $conn->prepare("UPDATE series SET status = 'ARQUIVADO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-info">Série arquivada com sucesso.</div>';
        $stmt->close();
    }
    // Restaurar
    elseif (isset($_POST['restore_id'])) {
        $id = (int)$_POST['restore_id'];
        $stmt = $conn->prepare("UPDATE series SET status = 'ATIVO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Série restaurada com sucesso!</div>';
        $stmt->close();
    }
    // Excluir Permanentemente
    elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];

        // Get the status of the series being deleted
        $series_status_stmt = $conn->prepare("SELECT status FROM series WHERE id = ?");
        $series_status_stmt->bind_param("i", $id);
        $series_status_stmt->execute();
        $series_status_stmt->bind_result($series_status);
        $series_status_stmt->fetch();
        $series_status_stmt->close();

        // Check for turmas associated with this series
        $check_turmas_stmt = $conn->prepare("SELECT COUNT(*) FROM turmas WHERE serie_id = ?");
        $check_turmas_stmt->bind_param("i", $id);
        $check_turmas_stmt->execute();
        $check_turmas_stmt->bind_result($turmas_count);
        $check_turmas_stmt->fetch();
        $check_turmas_stmt->close();

        // Check for active livros associated with this series
        $check_active_livros_stmt = $conn->prepare("SELECT COUNT(*) FROM livros WHERE serie_id = ? AND status = 'ATIVO'");
        $check_active_livros_stmt->bind_param("i", $id);
        $check_active_livros_stmt->execute();
        $check_active_livros_stmt->bind_result($active_livros_count);
        $check_active_livros_stmt->fetch();
        $check_active_livros_stmt->close();

        if ($series_status == 'ATIVO') {
            $mensagem = '<div class="alert alert-danger">Não é possível excluir esta série, pois ela está ATIVA. Arquive-a primeiro.</div>';
        } elseif ($active_livros_count > 0) {
            $mensagem = '<div class="alert alert-danger">Não é possível excluir esta série, pois ela ainda possui livros ATIVOS associados. Arquive-os primeiro.</div>';
        } else {
            // Get all turmas for the current serie
            $turmas_to_delete_stmt = $conn->prepare("SELECT id FROM turmas WHERE serie_id = ?");
            $turmas_to_delete_stmt->bind_param("i", $id);
            $turmas_to_delete_stmt->execute();
            $turmas_result = $turmas_to_delete_stmt->get_result();
            $turmas_ids = [];
            while ($row = $turmas_result->fetch_assoc()) {
                $turmas_ids[] = $row['id'];
            }
            $turmas_to_delete_stmt->close();

            if (!empty($turmas_ids)) {
                // For each turma, set estudante.turma_id to NULL
                $placeholders = implode(',', array_fill(0, count($turmas_ids), '?'));
                $types = str_repeat('i', count($turmas_ids));
                $update_estudantes_stmt = $conn->prepare("UPDATE estudantes SET turma_id = NULL WHERE turma_id IN ($placeholders)");
                $update_estudantes_stmt->bind_param($types, ...$turmas_ids);
                if (!$update_estudantes_stmt->execute()) {
                    $mensagem = '<div class="alert alert-danger">Erro ao desassociar estudantes: ' . $update_estudantes_stmt->error . '</div>';
                    $update_estudantes_stmt->close();
                    goto end_series_delete;
                }
                $update_estudantes_stmt->close();

                // Now, delete the turmas
                $delete_turmas_stmt = $conn->prepare("DELETE FROM turmas WHERE serie_id = ?");
                $delete_turmas_stmt->bind_param("i", $id);
                if (!$delete_turmas_stmt->execute()) {
                    $mensagem = '<div class="alert alert-danger">Erro ao excluir turmas: ' . $delete_turmas_stmt->error . '</div>';
                    $delete_turmas_stmt->close();
                    goto end_series_delete;
                }
                $delete_turmas_stmt->close();
            }

            // Delete all associated livros
            $delete_livros_stmt = $conn->prepare("DELETE FROM livros WHERE serie_id = ?");
            $delete_livros_stmt->bind_param("i", $id);
            if (!$delete_livros_stmt->execute()) {
                $mensagem = '<div class="alert alert-danger">Erro ao excluir livros: ' . $delete_livros_stmt->error . '</div>';
                $delete_livros_stmt->close();
                goto end_series_delete;
            }
            $delete_livros_stmt->close();

            // Now attempt to delete the series
            $stmt = $conn->prepare("DELETE FROM series WHERE id = ?");
            $stmt->bind_param("i", $id);
            try {
                if ($stmt->execute()) {
                    $mensagem = '<div class="alert alert-success">Série ID ' . $id . ' e suas dependências excluídas permanentemente!</div>';
                } else {
                    $mensagem = '<div class="alert alert-danger">Erro ao excluir série: ' . $stmt->error . '</div>';
                }
            } catch (mysqli_sql_exception $e) {
                $mensagem = '<div class="alert alert-danger">Erro inesperado ao excluir série ID ' . $id . ': ' . $e->getMessage() . '</div>';
            }
            $stmt->close();
        }
        end_series_delete:;
    }
    // Adicionar Novo
    elseif (isset($_POST['nome_serie'])) {
        $nome = $conn->real_escape_string(trim($_POST['nome_serie']));
        $curso_id = (int)$_POST['curso_id'];
        if (!empty($nome) && $curso_id > 0) {
            $stmt = $conn->prepare("INSERT INTO series (nome, curso_id) VALUES (?, ?)");
            $stmt->bind_param("si", $nome, $curso_id);
            if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Série adicionada com sucesso!</div>';
            $stmt->close();
        }
    }
}

$status_filter = ($view == 'active') ? 'ATIVO' : 'ARQUIVADO';
$cursos = $conn->query("SELECT * FROM cursos WHERE status = 'ATIVO' ORDER BY nome");
$series = $conn->query("SELECT s.id, s.nome, c.nome as curso_nome FROM series s JOIN cursos c ON s.curso_id = c.id WHERE s.status = '$status_filter' ORDER BY c.nome, s.nome");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Séries</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gerenciar Séries (<?php echo $view == 'active' ? 'Ativas' : 'Arquivadas'; ?>)</h2>
            <div>
                <?php if ($view == 'active'): ?>
                    <a href="?view=archived" class="btn btn-secondary">Ver Arquivadas</a>
                <?php else: ?>
                    <a href="?view=active" class="btn btn-secondary">Ver Ativas</a>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $mensagem; ?>

        <?php if ($view == 'active'): ?>
        <div class="card mb-4">
            <div class="card-header">Adicionar Nova Série</div>
            <div class="card-body">
                <form action="gerenciar_series.php" method="POST">
                    <div class="row">
                        <div class="col-md-5"><label class="form-label">Nome da Série</label><input type="text" class="form-control" name="nome_serie" required></div>
                        <div class="col-md-5"><label class="form-label">Curso</label><select class="form-select" name="curso_id" required><option value="">Selecione...</option><?php while($curso = $cursos->fetch_assoc()): ?><option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nome']); ?></option><?php endwhile; ?></select></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100">Adicionar</button></div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Séries <?php echo $view == 'active' ? 'Ativas' : 'Arquivadas'; ?></div>
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
                                <th><input type="checkbox" id="select_all_series"></th>
                                <th>Nome da Série</th>
                                <th>Curso</th>
                                <th style="width: 220px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $series->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_series[]" value="<?php echo $row['id']; ?>"></td>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo htmlspecialchars($row['curso_nome']); ?></td>
                                <td>
                                    <?php if ($view == 'active'): ?>
                                        <a href="editar_serie.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <form action="" method="POST" style="display: inline;"><input type="hidden" name="archive_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-secondary">Arquivar</button></form>
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
    document.getElementById('select_all_series').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_series[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    function confirmBulkAction(form) {
        const selectElement = form.elements['bulk_action'];
        if (selectElement.value === 'delete_selected') {
            return confirm('ATENÇÃO! Você está prestes a EXCLUIR PERMANENTEMENTE as séries selecionadas. Esta ação não pode ser desfeita. Deseja continuar?');
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
        if (confirm('ATENÇÃO! Isso excluirá a série permanentemente. Deseja continuar?')) {
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