<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$ano = $data['ano'] ?? null;

if (empty($ano)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ano letivo é obrigatório.']);
    exit;
}

$conn = connect_db();

// Verifica se o ano já existe
$check_stmt = $conn->prepare("SELECT id FROM anos_letivos WHERE ano = ?");
$check_stmt->bind_param("i", $ano);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Ano letivo já existe.']);
    exit;
}
$check_stmt->close();

$stmt = $conn->prepare("INSERT INTO anos_letivos (ano) VALUES (?)");
$stmt->bind_param("i", $ano);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Ano letivo adicionado com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar ano letivo: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>