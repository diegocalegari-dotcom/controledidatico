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
$conservacao_reposicao = $data['conservacao_reposicao'] ?? null;
$ano_letivo = date('Y'); // Assume current year for the reserve

$valid_conservacao = ['ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO'];

if (empty($emprestimo_id) || empty($conservacao_reposicao) || !in_array($conservacao_reposicao, $valid_conservacao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos ou inválidos para marcar como perdido.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    // 1. Get livro_id from the loan
    $stmt_get_livro = $conn->prepare("SELECT livro_id FROM emprestimos WHERE id = ?");
    $stmt_get_livro->bind_param("i", $emprestimo_id);
    $stmt_get_livro->execute();
    $result_livro = $stmt_get_livro->get_result();
    if ($result_livro->num_rows === 0) {
        throw new Exception('Empréstimo não encontrado.');
    }
    $livro_id = $result_livro->fetch_assoc()['livro_id'];
    $stmt_get_livro->close();

        // 2. Update loan to be marked as lost
    $stmt_update_emprestimo = $conn->prepare("UPDATE emprestimos SET dado_como_perdido = 1 WHERE id = ?");
    $stmt_update_emprestimo->bind_param("i", $emprestimo_id);
    $stmt_update_emprestimo->execute();
    if ($stmt_update_emprestimo->affected_rows === 0) {
        throw new Exception('Não foi possível atualizar o status do empréstimo.');
    }
    $stmt_update_emprestimo->close();

    // 3. Decrement the technical reserve for the specified conservation status
    // First, ensure the reserve entry exists
    $stmt_ensure_reserve = $conn->prepare(
        "INSERT INTO reserva_tecnica (livro_id, conservacao, ano_letivo, quantidade) VALUES (?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE quantidade = quantidade"
    );
    $stmt_ensure_reserve->bind_param("iss", $livro_id, $conservacao_reposicao, $ano_letivo);
    $stmt_ensure_reserve->execute();
    $stmt_ensure_reserve->close();

    // Then, decrement
    $stmt_decrement_reserve = $conn->prepare(
        "UPDATE reserva_tecnica SET quantidade = GREATEST(0, quantidade - 1)
         WHERE livro_id = ? AND conservacao = ? AND ano_letivo = ?"
    );
    $stmt_decrement_reserve->bind_param("iss", $livro_id, $conservacao_reposicao, $ano_letivo);
    $stmt_decrement_reserve->execute();
    // We don't throw an error if affected_rows is 0, as it might just mean the reserve was already at 0.
    $stmt_decrement_reserve->close();

    // If all queries were successful, commit the transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Livro marcado como perdido e reserva atualizada!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na transação: ' . $e->getMessage()]);
}

$conn->close();
?>