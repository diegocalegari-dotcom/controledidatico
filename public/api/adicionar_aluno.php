<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$nome = $data['nome'] ?? '';
$cgm = $data['cgm'] ?? '';
$turma_id = $data['turma_id'] ?? 0;

if (empty($nome) || empty($cgm) || empty($turma_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome, CGM e ID da turma são obrigatórios.']);
    exit;
}

$conn = connect_db();

// Verifica se o CGM já existe
$check_stmt = $conn->prepare("SELECT id FROM estudantes WHERE cgm = ?");
$check_stmt->bind_param("s", $cgm);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Já existe um aluno cadastrado com este CGM.']);
    exit;
}
$check_stmt->close();

$stmt = $conn->prepare("INSERT INTO estudantes (nome, cgm, turma_id, situacao) VALUES (?, ?, ?, 'MATRICULADO')");
$stmt->bind_param("ssi", $nome, $cgm, $turma_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Aluno adicionado com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar o aluno.']);
}

$stmt->close();
$conn->close();
?>
