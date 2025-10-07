<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

// Garante que a requisição seja do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Pega os dados do corpo da requisição (enviados como JSON)
$input = json_decode(file_get_contents('php://input'), true);

// Validação dos dados de entrada
$isbn = $input['isbn'] ?? '';
$titulo = $input['titulo'] ?? '';
$autor = $input['autor'] ?? null;
$materia_id = filter_var($input['materia_id'] ?? null, FILTER_VALIDATE_INT);
$serie_id = filter_var($input['serie_id'] ?? null, FILTER_VALIDATE_INT);
$quantidade = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_INT);

if (empty($isbn) || empty($titulo) || $materia_id === false || $serie_id === false || $quantidade === false || $quantidade <= 0) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos. Verifique todos os campos.']);
    exit;
}

$conn = connect_db();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        "INSERT INTO livros (isbn, titulo, autor, materia_id, serie_id, status) VALUES (?, ?, ?, ?, ?, 'ATIVO')"
    );

    if (!$stmt) {
        throw new Exception("Falha ao preparar a query: " . $conn->error);
    }

    $stmt->bind_param("sssii", $isbn, $titulo, $autor, $materia_id, $serie_id);

    $sucessos = 0;
    for ($i = 0; $i < $quantidade; $i++) {
        if ($stmt->execute()) {
            $sucessos++;
        } else {
            // Se uma inserção falhar, lança uma exceção para reverter a transação
            throw new Exception("Erro ao inserir a cópia " . ($i + 1) . ": " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true, 'message' => $sucessos . ' cópias do livro foram adicionadas com sucesso!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>