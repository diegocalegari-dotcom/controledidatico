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
$conservacao_devolucao = $data['conservacao'] ?? '';

if (empty($emprestimo_id) || empty($conservacao_devolucao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos para devolução.']);
    exit;
}

$conn = connect_db();

$stmt = $conn->prepare("UPDATE emprestimos SET status = 'Devolvido', data_devolucao = CURDATE(), conservacao_devolucao = ? WHERE id = ?");
$stmt->bind_param("si", $conservacao_devolucao, $emprestimo_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Devolução registrada com sucesso!']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Empréstimo não encontrado ou já atualizado.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar a devolução: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
