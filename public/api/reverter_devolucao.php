<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

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

$stmt = $conn->prepare("UPDATE emprestimos SET status = 'Emprestado', data_devolucao = NULL, conservacao_devolucao = NULL WHERE id = ? AND status = 'Devolvido'");
$stmt->bind_param("i", $emprestimo_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Devolução revertida com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma devolução encontrada para reverter.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao reverter a devolução.']);
}

$stmt->close();
$conn->close();
?>