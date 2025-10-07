<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validação
$livro_base_isbn = $input['livro_base_isbn'] ?? null;
$quantidade = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_INT);
$conservacao = $input['conservacao'] ?? null;

if (empty($livro_base_isbn) || !$quantidade || $quantidade <= 0 || empty($conservacao)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos.']);
    exit;
}

$conn = connect_db();

// 1. Encontrar as IDs dos livros que podem ser removidos
$stmt_select = $conn->prepare("
    SELECT l.id
    FROM livros l
    LEFT JOIN emprestimos em ON l.id = em.livro_id AND em.status = 'Emprestado'
    WHERE
        l.isbn = ?
        AND l.conservacao = ?
        AND l.status = 'ATIVO'
        AND em.id IS NULL
    LIMIT ?
");

$stmt_select->bind_param("ssi", $livro_base_isbn, $conservacao, $quantidade);
$stmt_select->execute();
$result = $stmt_select->get_result();

$ids_para_remover = [];
while ($row = $result->fetch_assoc()) {
    $ids_para_remover[] = $row['id'];
}
$stmt_select->close();

$copias_encontradas = count($ids_para_remover);

if ($copias_encontradas === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Nenhuma cópia disponível para remoção com os critérios especificados (pode ser que todas estejam emprestadas).']);
    $conn->close();
    exit;
}

// 2. Arquivar os livros encontrados
$conn->begin_transaction();
try {
    $ids_placeholder = implode(',', array_fill(0, $copias_encontradas, '?'));
    $types = str_repeat('i', $copias_encontradas);

    $stmt_update = $conn->prepare("UPDATE livros SET status = 'ARQUIVADO' WHERE id IN ($ids_placeholder)");
    $stmt_update->bind_param($types, ...$ids_para_remover);

    if (!$stmt_update->execute()) {
        throw new Exception("Erro ao arquivar os livros: " . $stmt_update->error);
    }
    
    $stmt_update->close();
    $conn->commit();

    echo json_encode(['success' => true, 'message' => $copias_encontradas . ' cópia(s) removida(s) do estoque (arquivada(s)).']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>