<?php
require_once '../config/database.php';
$conn = connect_db();

$serie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($serie_id <= 0) {
    header("Location: gerenciar_series.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $conn->real_escape_string(trim($_POST['nome']));
    $curso_id = (int)$_POST['curso_id'];
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("UPDATE series SET nome = ?, curso_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $nome, $curso_id, $id);
    if ($stmt->execute()) {
        header("Location: gerenciar_series.php");
        exit;
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM series WHERE id = ?");
$stmt->bind_param("i", $serie_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $serie = $result->fetch_assoc();
} else {
    header("Location: gerenciar_series.php");
    exit;
}

$cursos = $conn->query("SELECT * FROM cursos ORDER BY nome");
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Série</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Editar Série</h2>
        <div class="card">
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="id" value="<?php echo $serie_id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome da Série</label>
                        <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($serie['nome']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Curso</label>
                        <select class="form-select" name="curso_id" required>
                            <?php while($curso = $cursos->fetch_assoc()): ?>
                            <option value="<?php echo $curso['id']; ?>" <?php echo ($curso['id'] == $serie['curso_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['nome']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="gerenciar_series.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>