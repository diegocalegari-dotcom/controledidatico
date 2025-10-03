<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

$aluno_id = $_GET['aluno_id'] ?? null;

if (empty($aluno_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do aluno não fornecido.']);
    exit;
}

$conn = connect_db();

$query = "
    SELECT
        e.ano_letivo,
        l.titulo as livro_titulo,
        l.isbn,
        e.status,
        e.conservacao_entrega,
        e.conservacao_devolucao,
        e.data_entrega,
        e.data_devolucao
    FROM emprestimos e
    JOIN livros l ON e.livro_id = l.id
    WHERE e.estudante_id = ?
    ORDER BY e.ano_letivo DESC, e.data_entrega DESC
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $aluno_id);
$stmt->execute();
$result = $stmt->get_result();

$historico = [];
while ($row = $result->fetch_assoc()) {
    $ano = $row['ano_letivo'];
    if (!isset($historico[$ano])) {
        $historico[$ano] = [];
    }
    $historico[$ano][] = $row;
}

echo json_encode(['success' => true, 'historico' => $historico]);

$stmt->close();
$conn->close();
?>