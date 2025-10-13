<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$livro_id = $data['livro_id'] ?? null;
$conservacao = $data['conservacao'] ?? null;
$quantidade = $data['quantidade'] ?? null;
$ano_letivo = $data['ano_letivo'] ?? date('Y'); // Default to current year

$valid_conservacao = ['ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO'];

if (!$livro_id || !$conservacao || !in_array($conservacao, $valid_conservacao) || !is_numeric($quantidade) || $quantidade < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$conn = connect_db();

$sql = "INSERT INTO reserva_tecnica (livro_id, conservacao, quantidade, ano_letivo)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isis", $livro_id, $conservacao, $quantidade, $ano_letivo);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reserva técnica atualizada com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a reserva técnica: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>