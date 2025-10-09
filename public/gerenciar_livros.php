<?php
require_once '../config/database.php';
$conn = connect_db();

$mensagem = '';
$view = isset($_GET['view']) && $_GET['view'] == 'archived' ? 'archived' : 'active';

// Ações POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Arquivar
    if (isset($_POST['archive_id'])) {
        $id = (int)$_POST['archive_id'];
        $stmt = $conn->prepare("UPDATE livros SET status = 'ARQUIVADO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-info">Livro arquivado com sucesso.</div>';
    }
    // Restaurar
    elseif (isset($_POST['restore_id'])) {
        $id = (int)$_POST['restore_id'];
        $stmt = $conn->prepare("UPDATE livros SET status = 'ATIVO' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Livro restaurado com sucesso!</div>';
    }
    // Excluir
    elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM livros WHERE id = ?");
        $stmt->bind_param("i", $id);
        try {
            if ($stmt->execute()) $mensagem = '<div class="alert alert-success">Livro excluído permanentemente!</div>';
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) $mensagem = '<div class="alert alert-danger">Não é possível excluir. Este livro já foi entregue a um ou mais alunos.</div>';
            else $mensagem = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
    }
    // Adicionar
    elseif (isset($_POST['titulo']) && isset($_POST['serie_ids'])) { // Trigger alterado para um campo obrigatório e verifica serie_ids
        $isbn = !empty($_POST['isbn']) ? $_POST['isbn'] : null; // Converte string vazia para NULL
        $titulo = $_POST['titulo'];
        $autor = $_POST['autor'];
        $materia_id = $_POST['materia_id'];
        $serie_ids = $_POST['serie_ids']; // Array de IDs de série

        $success_count = 0;
        $error_messages = [];

        foreach ($serie_ids as $serie_id) {
            $stmt = $conn->prepare("INSERT INTO livros (isbn, titulo, autor, materia_id, serie_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $isbn, $titulo, $autor, $materia_id, $serie_id);
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_messages[] = "Erro ao adicionar livro para Série ID {$serie_id}: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        }

        if ($success_count > 0) {
            $mensagem = '<div class="alert alert-success">' . $success_count . ' livro(s) adicionado(s) com sucesso!</div>';
        }
        if (!empty($error_messages)) {
            $mensagem .= '<div class="alert alert-danger">' . implode('<br>', $error_messages) . '</div>';
        }
    }
}

// Buscas para preencher a página
$status_filter = ($view == 'active') ? 'ATIVO' : 'ARQUIVADO';
$materias = $conn->query("SELECT * FROM materias WHERE status = 'ATIVO' ORDER BY nome");
$series = $conn->query("SELECT series.id, series.nome, cursos.nome as curso_nome FROM series JOIN cursos ON series.curso_id = cursos.id WHERE series.status = 'ATIVO' ORDER BY cursos.nome, series.nome");
$livros = $conn->query("SELECT l.*, m.nome as materia_nome, s.nome as serie_nome FROM livros l JOIN materias m ON l.materia_id = m.id JOIN series s ON l.serie_id = s.id WHERE l.status = '$status_filter' ORDER BY l.titulo");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Livros</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="gerenciar_materias.php">Matérias</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_cursos.php">Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_series.php">Séries</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gerenciar_livros.php">Livros</a></li>
                    <li class="nav-item"><a class="nav-link" href="importar.php">Importar Alunos</a></li>
                    <li class="nav-item"><a class="nav-link" href="configuracoes.php">Configurações</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gerenciar Livros (<?php echo $view == 'active' ? 'Ativos' : 'Arquivados'; ?>)</h2>
            <div><a href="?view=<?php echo $view == 'active' ? 'archived' : 'active'; ?>" class="btn btn-secondary">Ver <?php echo $view == 'active' ? 'Arquivados' : 'Ativos'; ?></a></div>
        </div>
        <?php echo $mensagem; ?>

        <?php if ($view == 'active'): ?>
        <div class="card mb-4">
            <div class="card-header">Adicionar Novo Livro</div>
            <div class="card-body">
                <form action="gerenciar_livros.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">ISBN</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="isbn" name="isbn">
                                <button class="btn btn-outline-secondary" type="button" id="buscar-isbn">Buscar</button>
                            </div>
                        </div>
                        <div class="col-md-8"><label class="form-label">Título</label><input type="text" class="form-control" id="titulo" name="titulo" required></div>
                        <div class="col-md-12"><label class="form-label">Autor(es)</label><input type="text" class="form-control" id="autor" name="autor"></div>
                        <div class="col-md-6"><label class="form-label">Matéria</label><select class="form-select" name="materia_id" required><option value="">Selecione...</option><?php while($m = $materias->fetch_assoc()) echo "<option value='{$m['id']}'>".htmlspecialchars($m['nome'])."</option>"; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Série</label><select class="form-select" name="serie_ids[]" multiple size="5" required><option value="">Selecione...</option><?php while($s = $series->fetch_assoc()) echo "<option value='{$s['id']}'>".htmlspecialchars($s['curso_nome'] . ' - ' . $s['nome'])."</option>"; ?></select></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Adicionar Livro</button></div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Livros <?php echo $view == 'active' ? 'Ativos' : 'Arquivados'; ?></div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-sm">
                    <thead><tr><th>ISBN</th><th>Título</th><th>Matéria</th><th>Série</th><th style="width: 220px;">Ações</th></tr></thead>
                    <tbody>
                        <?php while($row = $livros->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['isbn']); ?></td>
                            <td><?php echo htmlspecialchars($row['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($row['materia_nome']); ?></td>
                            <td><?php echo htmlspecialchars($row['serie_nome']); ?></td>
                            <td>
                                <?php if ($view == 'active'): ?>
                                    <a href="editar_livro.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <form action="" method="POST" style="display: inline;"><input type="hidden" name="archive_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-secondary">Arquivar</button></form>
                                <?php else: ?>
                                    <form action="?view=archived" method="POST" style="display: inline;"><input type="hidden" name="restore_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-success">Restaurar</button></form>
                                    <form action="?view=archived" method="POST" onsubmit="return confirm('Excluir permanentemente?');" style="display: inline;"><input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Excluir</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

    <script>
    document.getElementById('buscar-isbn').addEventListener('click', async function() {
        const isbnInput = document.getElementById('isbn');
        const isbn = isbnInput.value.trim();
        if (!isbn) {
            alert('Por favor, insira um ISBN.');
            return;
        }

        const googleUrl = `https://www.googleapis.com/books/v1/volumes?q=isbn:${isbn}`;
        const openLibraryUrl = `https://openlibrary.org/api/books?bibkeys=ISBN:${isbn}&format=json&jscmd=data`;
        
        const originalButtonText = this.textContent;
        this.textContent = 'Buscando...';
        this.disabled = true;

        try {
            // 1. Tenta Google Books
            let response = await fetch(googleUrl);
            let data = await response.json();

            if (data.totalItems > 0 && data.items[0].volumeInfo) {
                const bookInfo = data.items[0].volumeInfo;
                document.getElementById('titulo').value = bookInfo.title || '';
                document.getElementById('autor').value = bookInfo.authors ? bookInfo.authors.join(', ') : '';
                alert('Livro encontrado no Google Books!');
            } else {
                // 2. Se não encontrar, tenta Open Library
                alert('Não encontrado no Google Books. Tentando na Open Library...');
                response = await fetch(openLibraryUrl);
                data = await response.json();
                const bookKey = `ISBN:${isbn}`;

                if (data[bookKey]) {
                    const bookInfo = data[bookKey];
                    document.getElementById('titulo').value = bookInfo.title || '';
                    document.getElementById('autor').value = bookInfo.authors ? bookInfo.authors.map(author => author.name).join(', ') : '';
                    alert('Livro encontrado na Open Library!');
                } else {
                    alert('Nenhum livro encontrado para este ISBN em nenhuma das fontes.');
                }
            }
        } catch (error) {
            console.error('Erro ao buscar ISBN:', error);
            alert('Ocorreu um erro ao buscar as informações do livro.');
        } finally {
            this.textContent = originalButtonText;
            this.disabled = false;
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>