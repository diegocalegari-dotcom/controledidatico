<?php
require_once '../config/database.php';
$conn = connect_db();

$mensagem = '';
$materia_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Se o ID for inválido, redireciona para a página de gerenciamento
if ($materia_id <= 0) {
    header("Location: gerenciar_materias.php");
    exit;
}

// Processa a atualização do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nome_materia'])) {
    $novo_nome = $conn->real_escape_string(trim($_POST['nome_materia']));
    $id_update = (int)$_POST['id'];

    if (!empty($novo_nome) && $id_update > 0) {
        $stmt = $conn->prepare("UPDATE materias SET nome = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_nome, $id_update);
        
        if ($stmt->execute()) {
            // Redireciona para a página principal para evitar reenvio do formulário
            header("Location: gerenciar_materias.php?status=success");
            exit;
        } else {
            $mensagem = '<div class="alert alert-danger">Erro ao atualizar a matéria.</div>';
        }
        $stmt->close();
    }
}

// Busca os dados da matéria para preencher o formulário
$stmt = $conn->prepare("SELECT nome FROM materias WHERE id = ?");
$stmt->bind_param("i", $materia_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $materia = $result->fetch_assoc();
} else {
    // Se não encontrar a matéria, redireciona
    header("Location: gerenciar_materias.php");
    exit;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Matéria</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Editar Matéria</h2>
        <?php echo $mensagem; ?>

        <div class="card">
            <div class="card-header">Alterar Nome da Matéria</div>
            <div class="card-body">
                <form action="editar_materia.php?id=<?php echo $materia_id; ?>" method="POST">
                    <input type="hidden" name="id" value="<?php echo $materia_id; ?>">
                    <div class="mb-3">
                        <label for="nome_materia" class="form-label">Nome da Matéria</label>
                        <input type="text" class="form-control" id="nome_materia" name="nome_materia" value="<?php echo htmlspecialchars($materia['nome']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <a href="gerenciar_materias.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
