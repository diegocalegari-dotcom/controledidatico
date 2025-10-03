<?php
require_once '../config/database.php';
$conn = connect_db();

$mensagem = '';
$view = isset($_GET['view']) && $_GET['view'] == 'archived' ? 'archived' : 'active';

// Lógica de Ações
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        $stmt = $conn->prepare("DELETE FROM series WHERE id = ?");
        $stmt->bind_param("i", $id);
        try {
            if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Série excluída permanentemente!</div>';
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                $mensagem = '<div class="alert alert-danger">Não é possível excluir esta série, pois está em uso por turmas ou livros.</div>';
            } else {
                $mensagem = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            }
        }
        $stmt->close();
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="gerenciar_materias.php">Matérias</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_cursos.php">Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_series.php">Séries</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_livros.php">Livros</a></li>
                    <li class="nav-item"><a class="nav-link" href="importar.php">Importar Alunos</a></li>
                    <li class="nav-item"><a class="nav-link" href="configuracoes.php">Configurações</a></li>
                </ul>
            </div>
        </div>
    </nav>
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
                <table class="table table-striped">
                    <thead><tr><th>Nome da Série</th><th>Curso</th><th style="width: 220px;">Ações</th></tr></thead>
                    <tbody>
                        <?php while($row = $series->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nome']); ?></td>
                            <td><?php echo htmlspecialchars($row['curso_nome']); ?></td>
                            <td>
                                <?php if ($view == 'active'): ?>
                                    <a href="editar_serie.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <form action="" method="POST" style="display: inline;"><input type="hidden" name="archive_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-secondary">Arquivar</button></form>
                                <?php else: ?>
                                    <form action="?view=archived" method="POST" style="display: inline;"><input type="hidden" name="restore_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-success">Restaurar</button></form>
                                    <form action="?view=archived" method="POST" onsubmit="return confirm('ATENÇÃO! Isso excluirá a série permanentemente.');" style="display: inline;"><input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Excluir</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>