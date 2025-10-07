<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validação dos dados de entrada
$livro_base_isbn = $input['livro_base_isbn'] ?? null;
$quantidade = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_INT);
$conservacao = $input['conservacao'] ?? null;

$valid_conservacao = ['ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO', 'NOVO'];

if (empty($livro_base_isbn) || !$quantidade || $quantidade <= 0 || !in_array($conservacao, $valid_conservacao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos.']);
    exit;
}

$conn = connect_db();

// 1. Buscar os dados do livro base para usar como template
$stmt_template = $conn->prepare("SELECT * FROM livros WHERE isbn = ? LIMIT 1");
$stmt_template->bind_param("s", $livro_base_isbn);
$stmt_template->execute();
$template_result = $stmt_template->get_result();

if ($template_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Livro base com o ISBN fornecido não encontrado.']);
    exit;
}
$template_livro = $template_result->fetch_assoc();
$stmt_template->close();

// 2. Inserir as novas cópias
$conn->begin_transaction();
try {
    $stmt_insert = $conn->prepare(
        "INSERT INTO livros (isbn, titulo, autor, materia_id, serie_id, status, conservacao) VALUES (?, ?, ?, ?, ?, 'ATIVO', ?)"
    );

    for ($i = 0; $i < $quantidade; $i++) {
        $stmt_insert->bind_param(
            "sssiss",
            $template_livro['isbn'],
            $template_livro['titulo'],
            $template_livro['autor'],
            $template_livro['materia_id'],
            $template_livro['serie_id'],
            $conservacao
        );
        if (!$stmt_insert->execute()) {
            throw new Exception("Erro ao inserir a cópia " . ($i + 1) . ": " . $stmt_insert->error);
        }
    }

    $stmt_insert->close();
    $conn->commit();

    echo json_encode(['success' => true, 'message' => $quantidade . ' cópia(s) adicionada(s) com sucesso!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>