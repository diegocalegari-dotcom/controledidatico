<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$aluno_id = $data['aluno_id'] ?? 0;
$nova_turma_id = $data['nova_turma_id'] ?? 0;

if (empty($aluno_id) || empty($nova_turma_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos para remanejar o aluno.']);
    exit;
}

$conn = connect_db();

// Opcional: Verificar se o aluno tem empréstimos ativos antes de remanejar
// (Pode ser uma regra de negócio importante)

$stmt = $conn->prepare("UPDATE estudantes SET turma_id = ? WHERE id = ?");
$stmt->bind_param("ii", $nova_turma_id, $aluno_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Aluno remanejado com sucesso!']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado ou já está na turma de destino.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao remanejar o aluno.']);
}

$stmt->close();
$conn->close();
?>
