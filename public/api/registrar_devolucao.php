<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$emprestimo_id = $data['emprestimo_id'] ?? 0;
$conservacao_devolucao = $data['conservacao'] ?? '';
$ano_letivo = date('Y'); // Assume current year for the reserve

$valid_conservacao = ['ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO'];

if (empty($emprestimo_id) || empty($conservacao_devolucao) || !in_array($conservacao_devolucao, $valid_conservacao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos ou inválidos para devolução.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    // 1. Get loan details to check if it was marked as lost
    $stmt_get_loan = $conn->prepare("SELECT livro_id, dado_como_perdido FROM emprestimos WHERE id = ?");
    $stmt_get_loan->bind_param("i", $emprestimo_id);
    $stmt_get_loan->execute();
    $result_loan = $stmt_get_loan->get_result();

    if ($result_loan->num_rows === 0) {
        throw new Exception("Empréstimo não encontrado.");
    }
    $loan = $result_loan->fetch_assoc();
    $stmt_get_loan->close();

    // 2. If the book was marked as lost, increment the technical reserve
    if ($loan['dado_como_perdido'] == 1) {
        $livro_id = $loan['livro_id'];

        // Ensure the reserve entry exists before incrementing
        $stmt_ensure_reserve = $conn->prepare(
            "INSERT INTO reserva_tecnica (livro_id, conservacao, ano_letivo, quantidade) VALUES (?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE quantidade = quantidade"
        );
        $stmt_ensure_reserve->bind_param("iss", $livro_id, $conservacao_devolucao, $ano_letivo);
        $stmt_ensure_reserve->execute();
        $stmt_ensure_reserve->close();

        // Increment the reserve
        $stmt_increment_reserve = $conn->prepare(
            "UPDATE reserva_tecnica SET quantidade = quantidade + 1
             WHERE livro_id = ? AND conservacao = ? AND ano_letivo = ?"
        );
        $stmt_increment_reserve->bind_param("iss", $livro_id, $conservacao_devolucao, $ano_letivo);
        $stmt_increment_reserve->execute();
        $stmt_increment_reserve->close();
    }

    // 3. Update the loan status to 'Devolvido' and reset the 'lost' flag
    $stmt_update_loan = $conn->prepare(
        "UPDATE emprestimos 
         SET status = 'Devolvido', 
             data_devolucao = CURDATE(), 
             conservacao_devolucao = ?, 
             dado_como_perdido = 0 
         WHERE id = ?"
    );
    $stmt_update_loan->bind_param("si", $conservacao_devolucao, $emprestimo_id);
    $stmt_update_loan->execute();

    if ($stmt_update_loan->affected_rows === 0) {
        // This might happen if the status is already 'Devolvido', which is not an error in this context.
        // We can consider it a success if the loan exists.
    }
    $stmt_update_loan->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Devolução registrada com sucesso!',
        'devolucao' => [
            'emprestimo_id' => $emprestimo_id,
            'conservacao_devolucao' => $conservacao_devolucao
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na transação: ' . $e->getMessage()]);
}

$conn->close();
?>