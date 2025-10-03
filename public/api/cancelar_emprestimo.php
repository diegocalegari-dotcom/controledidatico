<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$emprestimo_id = $data['emprestimo_id'] ?? 0;

if (empty($emprestimo_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do empréstimo não fornecido.']);
    exit;
}

$conn = connect_db();

$stmt = $conn->prepare("DELETE FROM emprestimos WHERE id = ?");
$stmt->bind_param("i", $emprestimo_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Entrega cancelada com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao cancelar a entrega.']);
}

$stmt->close();
$conn->close();
?>