<?php
require_once '../config/database.php';
$conn = connect_db();

$livro_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($livro_id <= 0) {
    header("Location: gerenciar_livros.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("UPDATE livros SET isbn = ?, titulo = ?, autor = ?, materia_id = ?, serie_id = ? WHERE id = ?");
    $stmt->bind_param("sssiii", $_POST['isbn'], $_POST['titulo'], $_POST['autor'], $_POST['materia_id'], $_POST['serie_id'], $id);
    if ($stmt->execute()) {
        header("Location: gerenciar_livros.php");
        exit;
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM livros WHERE id = ?");
$stmt->bind_param("i", $livro_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $livro = $result->fetch_assoc();
} else {
    header("Location: gerenciar_livros.php");
    exit;
}

$materias = $conn->query("SELECT * FROM materias WHERE status = 'ATIVO' ORDER BY nome");
$series = $conn->query("SELECT series.id, series.nome, cursos.nome as curso_nome FROM series JOIN cursos ON series.curso_id = cursos.id WHERE series.status = 'ATIVO' ORDER BY cursos.nome, series.nome");

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Livro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Editar Livro</h2>
        <div class="card">
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="id" value="<?php echo $livro_id; ?>">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">ISBN</label><input type="text" class="form-control" name="isbn" value="<?php echo htmlspecialchars($livro['isbn']); ?>" required></div>
                        <div class="col-md-8"><label class="form-label">Título</label><input type="text" class="form-control" name="titulo" value="<?php echo htmlspecialchars($livro['titulo']); ?>" required></div>
                        <div class="col-md-12"><label class="form-label">Autor(es)</label><input type="text" class="form-control" name="autor" value="<?php echo htmlspecialchars($livro['autor']); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Matéria</label><select class="form-select" name="materia_id" required><?php while($m = $materias->fetch_assoc()) { $selected = ($m['id'] == $livro['materia_id']) ? 'selected' : ''; echo "<option value='{$m['id']}' $selected>".htmlspecialchars($m['nome'])."</option>"; } ?></select></div>
                        <div class="col-md-6"><label class="form-label">Série</label><select class="form-select" name="serie_id" required><?php while($s = $series->fetch_assoc()) { $selected = ($s['id'] == $livro['serie_id']) ? 'selected' : ''; echo "<option value='{$s['id']}' $selected>".htmlspecialchars($s['curso_nome'] . ' - ' . $s['nome'])."</option>"; } ?></select></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Salvar Alterações</button> <a href="gerenciar_livros.php" class="btn btn-secondary">Cancelar</a></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>