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
            $stmt = $conn->prepare("DELETE FROM emprestimos WHERE id = ?");
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Entrega cancelada com sucesso!'];
            } else {
                $response = ['success' => true, 'message' => 'A entrega já havia sido cancelada.'];
            }
            
            $stmt->close();
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