<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$conn = connect_db();
$data = json_decode(file_get_contents('php://input'), true);
$emprestimo_id = $data['emprestimo_id'] ?? 0;

if (empty($emprestimo_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do empréstimo não fornecido.']);
    exit;
}

try {
    $conn->begin_transaction();

    // Get loan details before changing it
    $stmt_get = $conn->prepare("SELECT estudante_id, livro_id, conservacao_entrega, ano_letivo FROM emprestimos WHERE id = ? AND status = 'Perdido'");
    $stmt_get->bind_param("i", $emprestimo_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Empréstimo com status 'Perdido' não encontrado.");
    }
    $loan = $result->fetch_assoc();
    $stmt_get->close();

    // Update status back to 'Emprestado'
    $stmt_update = $conn->prepare("UPDATE emprestimos SET status = 'Emprestado', dado_como_perdido = 0 WHERE id = ?");
    $stmt_update->bind_param("i", $emprestimo_id);
    $stmt_update->execute();
    $stmt_update->close();

    $conn->commit();

    // Return the reverted loan details for UI update
    echo json_encode([
        'success' => true,
        'message' => 'Status de perda revertido com sucesso!',
        'reverted_loan' => [
            'emprestimo_id' => $emprestimo_id,
            'aluno_id' => $loan['estudante_id'],
            'livro_id' => $loan['livro_id'],
            'conservacao_entrega' => $loan['conservacao_entrega']
        ]
    ]);

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na transação: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>