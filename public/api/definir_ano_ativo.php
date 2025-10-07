<?php
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php"); // Redireciona para o dashboard se não for POST
    exit;
}

$ano_letivo = filter_var($_POST['ano_letivo'] ?? null, FILTER_VALIDATE_INT);
$redirect_to = $_POST['redirect_to'] ?? '/index.php';

if (!$ano_letivo) {
    // Se o ano não for válido, redireciona com erro ou para o dashboard
    header("Location: " . $redirect_to . "?error=ano_invalido");
    exit;
}

$conn = connect_db();

try {
    $stmt = $conn->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'ano_letivo_ativo'");
    $stmt->bind_param("s", $ano_letivo);
    
    if ($stmt->execute()) {
        // Redireciona de volta para a página anterior
        header("Location: " . $redirect_to);
        exit;
    } else {
        throw new Exception("Erro ao definir ano letivo ativo: " . $stmt->error);
    }
    
} catch (Exception $e) {
    // Em caso de erro, redireciona com uma mensagem de erro
    header("Location: " . $redirect_to . "?error=" . urlencode($e->getMessage()));
    exit;
} finally {
    $conn->close();
}
?>