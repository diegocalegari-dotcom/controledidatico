<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ano_letivo = filter_var($input['ano_letivo'] ?? null, FILTER_VALIDATE_INT);
$confirmacao = $input['confirmacao'] ?? '';

$frase_correta = 'resetar dados do ano letivo';

if (!$ano_letivo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ano letivo não especificado.']);
    exit;
}

if ($confirmacao !== $frase_correta) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A frase de confirmação está incorreta.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    error_log("API resetar_ano_letivo.php: Recebido ano_letivo = " . $ano_letivo);
    $total_emprestimos_apagados = 0;
    $total_estudantes_apagados = 0;
    $total_turmas_apagadas = 0;

    // 1. Apagar empréstimos do ano (diretamente pela coluna ano_letivo da tabela emprestimos)
    $stmt_emprestimos = $conn->prepare("DELETE FROM emprestimos WHERE ano_letivo = ?");
    $stmt_emprestimos->bind_param("s", $ano_letivo);
    $stmt_emprestimos->execute();
    $total_emprestimos_apagados = $stmt_emprestimos->affected_rows;
    error_log("API resetar_ano_letivo.php: " . $total_emprestimos_apagados . " empréstimos apagados.");
    $stmt_emprestimos->close();

    // 2. Apagar estudantes que pertencem a turmas do ano
    $stmt_estudantes = $conn->prepare("
        DELETE e FROM estudantes e
        JOIN turmas t ON e.turma_id = t.id
        WHERE t.ano_letivo = ?
    ");
    $stmt_estudantes->bind_param("s", $ano_letivo);
    $stmt_estudantes->execute();
    $total_estudantes_apagados = $stmt_estudantes->affected_rows;
    error_log("API resetar_ano_letivo.php: " . $total_estudantes_apagados . " estudantes apagados.");
    $stmt_estudantes->close();

    // 3. Apagar turmas do ano
    $stmt_turmas = $conn->prepare("DELETE FROM turmas WHERE ano_letivo = ?");
    $stmt_turmas->bind_param("s", $ano_letivo);
    $stmt_turmas->execute();
    $total_turmas_apagadas = $stmt_turmas->affected_rows;
    error_log("API resetar_ano_letivo.php: " . $total_turmas_apagadas . " turmas apagadas.");
    $stmt_turmas->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 
        'Dados do ano de ' . $ano_letivo . ' apagados com sucesso. ' .
        $total_emprestimos_apagados . ' empréstimos, ' .
        $total_estudantes_apagados . ' estudantes e ' .
        $total_turmas_apagadas . ' turmas foram afetados.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("API resetar_ano_letivo.php: Exceção: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor ao apagar dados: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>