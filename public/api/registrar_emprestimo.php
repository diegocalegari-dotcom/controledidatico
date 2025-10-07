<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$livro_id = $data['livro_id'] ?? 0;
$aluno_id = $data['aluno_id'] ?? 0;
$conservacao = $data['conservacao'] ?? '';
$ano_letivo = $data['ano_letivo'] ?? null;

if (empty($livro_id) || empty($aluno_id) || empty($conservacao) || empty($ano_letivo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (livro, aluno, conservação ou ano letivo).']);
    exit;
}

$conn = connect_db();

// Verifica se já não existe um empréstimo ativo para este livro/aluno NO MESMO ANO LETIVO
$check_stmt = $conn->prepare("SELECT id FROM emprestimos WHERE livro_id = ? AND estudante_id = ? AND ano_letivo = ? AND status != 'Devolvido'");
$check_stmt->bind_param("iis", $livro_id, $aluno_id, $ano_letivo);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Este livro já consta como entregue para este aluno no ano letivo de ' . $ano_letivo . '.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO emprestimos (livro_id, estudante_id, ano_letivo, data_entrega, conservacao_entrega, status) VALUES (?, ?, ?, CURDATE(), ?, 'Emprestado')");
$stmt->bind_param("iiss", $livro_id, $aluno_id, $ano_letivo, $conservacao);

if ($stmt->execute()) {
    $novo_id = $conn->insert_id;
    echo json_encode([
        'success' => true, 
        'message' => 'Entrega registrada com sucesso!', 
        'novo_emprestimo' => [
            'emprestimo_id' => $novo_id,
            'livro_id' => $livro_id,
            'aluno_id' => $aluno_id,
            'conservacao' => $conservacao
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar a entrega: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>