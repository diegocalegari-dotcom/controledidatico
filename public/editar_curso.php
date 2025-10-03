<?php
require_once '../config/database.php';
$conn = connect_db();

$mensagem = '';
$curso_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($curso_id <= 0) {
    header("Location: gerenciar_cursos.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $conn->real_escape_string(trim($_POST['nome']));
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("UPDATE cursos SET nome = ? WHERE id = ?");
    $stmt->bind_param("si", $nome, $id);
    if ($stmt->execute()) {
        header("Location: gerenciar_cursos.php");
        exit;
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT nome FROM cursos WHERE id = ?");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $curso = $result->fetch_assoc();
} else {
    header("Location: gerenciar_cursos.php");
    exit;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Curso</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Editar Curso</h2>
        <div class="card">
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="id" value="<?php echo $curso_id; ?>">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Curso</label>
                        <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($curso['nome']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="gerenciar_cursos.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>