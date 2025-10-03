<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erro ao decodificar o JSON.', 'received' => $raw_data]);
    exit;
}

$emprestimo_id = $data['emprestimo_id'] ?? 0;

if (empty($emprestimo_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do empréstimo não fornecido.']);
    exit;
}

$conn = connect_db();

$stmt = $conn->prepare("UPDATE emprestimos SET status = 'Perdido', data_devolucao = NULL, conservacao_devolucao = NULL WHERE id = ?");
$stmt->bind_param("i", $emprestimo_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Livro marcado como perdido.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Empréstimo não encontrado ou já atualizado.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao marcar como perdido: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
