<?php
// Inicia o buffer de saída para garantir uma resposta JSON limpa
ob_start();

// Configura os cabeçalhos e o ambiente
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/database.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response = ['success' => false, 'message' => 'Método não permitido.'];
} else {
    try {
        $conn = connect_db();

        $data = json_decode(file_get_contents('php://input'), true);
        $emprestimo_id = $data['emprestimo_id'] ?? 0;

        if (empty($emprestimo_id)) {
            http_response_code(400);
            $response = ['success' => false, 'message' => 'ID do empréstimo não fornecido.'];
        } else {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE emprestimos SET status = 'Emprestado', data_devolucao = NULL, conservacao_devolucao = NULL WHERE id = ? AND status = 'Devolvido'");
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $select_stmt = $conn->prepare("SELECT id as emprestimo_id, estudante_id as aluno_id, livro_id, conservacao_entrega FROM emprestimos WHERE id = ?");
                $select_stmt->bind_param("i", $emprestimo_id);
                $select_stmt->execute();
                $result = $select_stmt->get_result();
                $emprestimo_revertido = $result->fetch_assoc();
                $select_stmt->close();

                $response = ['success' => true, 'message' => 'Devolução revertida com sucesso!', 'reverted_loan' => $emprestimo_revertido];
            } else {
                $response = ['success' => false, 'message' => 'Nenhuma devolução encontrada para reverter.'];
            }
            
            $stmt->close();
            $conn->commit();
        }

    } catch (mysqli_sql_exception $e) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        http_response_code(500);
        error_log("Erro em reverter_devolucao.php: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Ocorreu um erro no servidor ao processar a solicitação.'];
    } finally {
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }
}

// Limpa o buffer e envia a resposta JSON final
ob_end_clean();
echo json_encode($response);
exit();
?>