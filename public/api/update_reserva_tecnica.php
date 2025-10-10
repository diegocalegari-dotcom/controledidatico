<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$livro_id = isset($data['livro_id']) ? (int)$data['livro_id'] : 0;
$conservacao = isset($data['conservacao']) ? $data['conservacao'] : '';
$quantidade = isset($data['quantidade']) ? (int)$data['quantidade'] : 0;
$ano_letivo = isset($data['ano_letivo']) ? (int)$data['ano_letivo'] : 0;
$action = isset($data['action_type']) ? $data['action_type'] : 'add';

if (!$livro_id || !$conservacao || !$ano_letivo || $quantidade <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos. A quantidade deve ser maior que zero.', 'received' => $data]);
    exit;
}

$conn = connect_db();

// Determina a quantidade a ser adicionada ou removida
$valor_ajuste = ($action === 'remove') ? -abs($quantidade) : abs($quantidade);

$sql = "
    INSERT INTO reserva_tecnica (livro_id, conservacao, ano_letivo, quantidade)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE quantidade = GREATEST(0, quantidade + ?);
";

$stmt = $conn->prepare($sql);
// Para o INSERT inicial, usamos a quantidade absoluta. Para o UPDATE, o valor de ajuste.
$stmt->bind_param("issii", $livro_id, $conservacao, $ano_letivo, abs($quantidade), $valor_ajuste);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reserva técnica atualizada com sucesso.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a reserva técnica.', 'db_error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>