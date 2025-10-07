<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$emprestimo_id = $data['emprestimo_id'] ?? 0;
$status = $data['status'] ?? '';
$conservacao = $data['conservacao'] ?? null;

if (empty($emprestimo_id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$conn = connect_db();
$sql = '';

switch ($status) {
    case 'Devolvido':
        $sql = "UPDATE emprestimos SET status = 'Devolvido', data_devolucao = CURDATE(), conservacao_devolucao = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $conservacao, $emprestimo_id);
        break;
    case 'Perdido':
        $sql = "UPDATE emprestimos SET status = 'Perdido', data_devolucao = NULL, conservacao_devolucao = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $emprestimo_id);
        break;
    case 'Emprestado': // Para reverter uma devolução/perda
        $sql = "UPDATE emprestimos SET status = 'Emprestado', data_devolucao = NULL, conservacao_devolucao = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $emprestimo_id);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status inválido.']);
        exit;
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status.']);
}

$stmt->close();
$conn->close();
?>