<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$aluno_id = $data['aluno_id'] ?? 0;

if (empty($aluno_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do aluno não fornecido.']);
    exit;
}

$conn = connect_db();

// Opcional: Adicionar verificação de empréstimos pendentes antes de remover.

$stmt = $conn->prepare("UPDATE estudantes SET turma_id = NULL WHERE id = ?");
$stmt->bind_param("i", $aluno_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Aluno removido da turma com sucesso!']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado ou já sem turma.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao remover o aluno da turma.']);
}

$stmt->close();
$conn->close();
?>
