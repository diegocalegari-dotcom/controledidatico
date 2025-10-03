<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$aluno_id = $data['aluno_id'] ?? 0;
$livros_ids = $data['livros_ids'] ?? [];
$conservacao = 'BOM'; // Padrão definido

if (empty($aluno_id) || empty($livros_ids) || !is_array($livros_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos ou malformados.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO emprestimos (livro_id, estudante_id, data_entrega, conservacao_entrega, status) VALUES (?, ?, CURDATE(), ?, 'Emprestado')");

    foreach ($livros_ids as $livro_id) {
        $stmt->bind_param("iis", $livro_id, $aluno_id, $conservacao);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Entrega em massa registrada com sucesso!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar entregas: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>