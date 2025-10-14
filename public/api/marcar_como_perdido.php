<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Get data from payload
$emprestimo_id = $data['emprestimo_id'] ?? 0;
$realizar_reposicao = $data['realizar_reposicao'] ?? false;
$conservacao_reposicao = $data['conservacao_reposicao'] ?? null;
$ano_letivo = date('Y');

// Validation
if (empty($emprestimo_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do empréstimo não fornecido.']);
    exit;
}
if ($realizar_reposicao && empty($conservacao_reposicao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado de conservação da reposição não fornecido.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    // 1. Get original loan details
    $stmt_get_loan = $conn->prepare("SELECT estudante_id, livro_id, ano_letivo FROM emprestimos WHERE id = ?");
    $stmt_get_loan->bind_param("i", $emprestimo_id);
    $stmt_get_loan->execute();
    $result_loan = $stmt_get_loan->get_result();
    if ($result_loan->num_rows === 0) {
        throw new Exception('Empréstimo original não encontrado.');
    }
    $loan = $result_loan->fetch_assoc();
    $stmt_get_loan->close();

    $aluno_id = $loan['estudante_id'];
    $livro_id = $loan['livro_id'];
    $ano_letivo_original = $loan['ano_letivo'];

    // 2. Update old loan status to 'Perdido'
    $stmt_update_old_loan = $conn->prepare("UPDATE emprestimos SET status = 'Perdido', dado_como_perdido = 1 WHERE id = ?");
    $stmt_update_old_loan->bind_param("i", $emprestimo_id);
    $stmt_update_old_loan->execute();
    $stmt_update_old_loan->close();

    $response_data = [];

    if ($realizar_reposicao) {
        // SCENARIO 1: Replace the book

        // 3a. Decrement the technical reserve
        $stmt_decrement_reserve = $conn->prepare(
            "UPDATE reserva_tecnica SET quantidade = GREATEST(0, quantidade - 1)
             WHERE livro_id = ? AND conservacao = ? AND ano_letivo = ?"
        );
        $stmt_decrement_reserve->bind_param("iss", $livro_id, $conservacao_reposicao, $ano_letivo);
        $stmt_decrement_reserve->execute();
        $stmt_decrement_reserve->close();

        // 3b. Create a new loan for the replacement book
        $stmt_new_loan = $conn->prepare(
            "INSERT INTO emprestimos (livro_id, estudante_id, ano_letivo, data_entrega, conservacao_entrega, status) 
             VALUES (?, ?, ?, CURDATE(), ?, 'Emprestado')"
        );
        $stmt_new_loan->bind_param("iiss", $livro_id, $aluno_id, $ano_letivo_original, $conservacao_reposicao);
        $stmt_new_loan->execute();
        $new_emprestimo_id = $conn->insert_id;
        $stmt_new_loan->close();

        $response_data = [
            'replacement_loan' => [
                'emprestimo_id' => $new_emprestimo_id,
                'aluno_id' => $aluno_id,
                'livro_id' => $livro_id,
                'conservacao_entrega' => $conservacao_reposicao,
                'status' => 'Emprestado'
            ],
            'lost_loan_id' => $emprestimo_id
        ];
        $message = 'Livro marcado como perdido e reposição registrada com sucesso!';

    } else {
        // SCENARIO 2: Do not replace the book
        $response_data = [
            'lost_loan' => [
                'emprestimo_id' => $emprestimo_id,
                'aluno_id' => $aluno_id,
                'livro_id' => $livro_id,
                'status' => 'Perdido'
            ]
        ];
        $message = 'Livro marcado como perdido com sucesso (sem reposição).';
    }

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'data' => $response_data
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na transação: ' . $e->getMessage()]);
}

$conn->close();
?>