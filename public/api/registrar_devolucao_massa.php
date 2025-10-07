<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$aluno_id = $input['aluno_id'] ?? null;
$ano_letivo = $input['ano_letivo'] ?? null;

if (empty($aluno_id) || empty($ano_letivo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do aluno e ano letivo são obrigatórios.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    // Primeiro, seleciona os livros que serão devolvidos para saber seus IDs
    $select_stmt = $conn->prepare("SELECT id, livro_id FROM emprestimos WHERE estudante_id = ? AND ano_letivo = ? AND status = 'Emprestado'");
    $select_stmt->bind_param('is', $aluno_id, $ano_letivo);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    $emprestimos_a_devolver = $result->fetch_all(MYSQLI_ASSOC);
    $select_stmt->close();

    if (empty($emprestimos_a_devolver)) {
        $conn->commit(); // Ou rollback(), tanto faz, nenhuma mudança foi feita.
        echo json_encode(['success' => false, 'message' => 'Nenhum livro pendente encontrado para este aluno no ano letivo selecionado.']);
        $conn->close();
        exit;
    }

    // Agora, atualiza os registros
    $update_stmt = $conn->prepare(
        "UPDATE emprestimos 
         SET status = 'Devolvido', data_devolucao = CURDATE(), conservacao_devolucao = 'BOM'
         WHERE estudante_id = ? AND ano_letivo = ? AND status = 'Emprestado'"
    );
    $update_stmt->bind_param('is', $aluno_id, $ano_letivo);
    $update_stmt->execute();
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();

    // Prepara os dados para o retorno
    $devolucoes_info = [];
    foreach ($emprestimos_a_devolver as $emp) {
        $devolucoes_info[] = [
            'emprestimo_id' => $emp['id'],
            'livro_id' => $emp['livro_id'],
            'conservacao_devolucao' => 'BOM'
        ];
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $affected_rows . ' livro(s) devolvido(s) com sucesso.', 'devolucoes' => $devolucoes_info]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} finally {
    if ($conn->ping()) {
        $conn->close();
    }
}
?>