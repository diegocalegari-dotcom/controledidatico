<?php
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$ano_id = $data['ano_id'] ?? null;
$nova_sessao = $data['sessao'] ?? '';

if (empty($ano_id) || !in_array($nova_sessao, ['ENTREGA', 'DEVOLUCAO'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$conn = connect_db();

$stmt = $conn->prepare("UPDATE anos_letivos SET sessao_ativa = ? WHERE id = ?");
$stmt->bind_param("si", $nova_sessao, $ano_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Sessão atualizada com sucesso!']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Nenhuma alteração necessária.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a sessão.']);
}

$stmt->close();
$conn->close();
?>