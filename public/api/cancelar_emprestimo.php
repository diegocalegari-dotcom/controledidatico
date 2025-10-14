<?php
// Inicia o buffer de saída para capturar qualquer saída prematura (ex: BOM, espaços)
ob_start();

// Configura os cabeçalhos e o ambiente
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';

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
            // Primeiro, buscar estudante_id e livro_id
            $stmt_select = $conn->prepare("SELECT estudante_id, livro_id FROM emprestimos WHERE id = ?");
            $stmt_select->bind_param("i", $emprestimo_id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            $emprestimo_data = $result->fetch_assoc();
            $stmt_select->close();

            if (!$emprestimo_data) {
                // Empréstimo não encontrado, pode já ter sido cancelado.
                $response = ['success' => true, 'message' => 'A entrega já havia sido cancelada ou não foi encontrada.'];
            } else {
                $aluno_id = $emprestimo_data['estudante_id']; // Corrigido para estudante_id
                $livro_id = $emprestimo_data['livro_id'];

                $stmt_delete = $conn->prepare("DELETE FROM emprestimos WHERE id = ?");
                $stmt_delete->bind_param("i", $emprestimo_id);
                $stmt_delete->execute();

                if ($stmt_delete->affected_rows > 0) {
                    $response = [
                        'success' => true, 
                        'message' => 'Entrega cancelada com sucesso!',
                        'aluno_id' => $aluno_id, // A chave continua aluno_id para o frontend
                        'livro_id' => $livro_id
                    ];
                } else {
                    // Se a deleção falhar mas o select funcionou, algo está estranho, mas tratamos como já cancelado.
                    $response = [
                        'success' => true, 
                        'message' => 'A entrega já havia sido cancelada.',
                        'aluno_id' => $aluno_id, // A chave continua aluno_id para o frontend
                        'livro_id' => $livro_id
                    ];
                }
                $stmt_delete->close();
            }
            $conn->close();
        }

    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        error_log("Erro em cancelar_emprestimo.php: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Ocorreu um erro no servidor ao processar a solicitação.'];
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erro geral em cancelar_emprestimo.php: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
    }
}

// Limpa qualquer saída que tenha sido bufferizada (remove lixo)
ob_end_clean();

// Envia a resposta JSON limpa
echo json_encode($response);

exit();
?>