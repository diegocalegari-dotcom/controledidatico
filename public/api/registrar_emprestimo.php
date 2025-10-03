<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$livro_id = $data['livro_id'] ?? 0;
$aluno_id = $data['aluno_id'] ?? 0;
$conservacao = $data['conservacao'] ?? '';

if (empty($livro_id) || empty($aluno_id) || empty($conservacao)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$conn = connect_db();

// Verifica se já não existe um empréstimo ativo para este livro/aluno
$check_stmt = $conn->prepare("SELECT id FROM emprestimos WHERE livro_id = ? AND estudante_id = ? AND status != 'Devolvido'");
$check_stmt->bind_param("ii", $livro_id, $aluno_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Este livro já consta como entregue para este aluno.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO emprestimos (livro_id, estudante_id, data_entrega, conservacao_entrega, status) VALUES (?, ?, CURDATE(), ?, 'Emprestado')");
$stmt->bind_param("iis", $livro_id, $aluno_id, $conservacao);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Entrega registrada com sucesso!']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar a entrega.']);
}

$stmt->close();
$conn->close();
?>