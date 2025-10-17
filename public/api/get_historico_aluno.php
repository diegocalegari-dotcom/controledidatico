<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

// Habilitar logs de erro
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

$aluno_id = $_GET['aluno_id'] ?? null;
error_log("get_historico_aluno.php: Recebido aluno_id = " . $aluno_id);

if (empty($aluno_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do aluno não fornecido.']);
    exit;
}

$conn = connect_db();

// 1. Pega o CGM do aluno_id atual
$stmt_cgm = $conn->prepare("SELECT cgm FROM estudantes WHERE id = ?");
$stmt_cgm->bind_param("i", $aluno_id);
$stmt_cgm->execute();
$result_cgm = $stmt_cgm->get_result();
if ($result_cgm->num_rows === 0) {
    error_log("get_historico_aluno.php: Aluno com id = $aluno_id não encontrado.");
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}
$cgm_atual = $result_cgm->fetch_assoc()['cgm'];
$stmt_cgm->close();
error_log("get_historico_aluno.php: CGM encontrado = " . $cgm_atual);

$ids_para_buscar = [$aluno_id];

// 2. Verifica se o CGM é prefixado e busca o ID do aluno original
if (preg_match('/^(\d{4})-(\d+)$/', $cgm_atual, $matches)) {
    $cgm_original = $matches[2];
    error_log("get_historico_aluno.php: CGM prefixado detectado. CGM Original = " . $cgm_original);
    
    $stmt_original_id = $conn->prepare("SELECT id FROM estudantes WHERE cgm = ?");
    $stmt_original_id->bind_param("s", $cgm_original);
    $stmt_original_id->execute();
    $result_original_id = $stmt_original_id->get_result();
    
    if ($result_original_id->num_rows > 0) {
        $original_id = $result_original_id->fetch_assoc()['id'];
        $ids_para_buscar[] = $original_id;
        error_log("get_historico_aluno.php: ID do aluno original encontrado = " . $original_id);
    } else {
        error_log("get_historico_aluno.php: Aluno com CGM original $cgm_original não encontrado.");
    }
    $stmt_original_id->close();
} else {
    error_log("get_historico_aluno.php: CGM não é prefixado.");
}

// 3. Monta a query para buscar o histórico de todos os IDs encontrados
$placeholders = implode(',', array_fill(0, count($ids_para_buscar), '?'));
$types = str_repeat('i', count($ids_para_buscar));

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
    WHERE e.estudante_id IN ($placeholders)
    ORDER BY e.ano_letivo DESC, e.data_entrega DESC
";
error_log("get_historico_aluno.php: Query SQL final = " . $query);
error_log("get_historico_aluno.php: IDs para buscar = " . implode(', ', $ids_para_buscar));

$stmt = $conn->prepare($query);
if ($stmt === false) {
    error_log("get_historico_aluno.php: Erro na preparação da query: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query: ' . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$ids_para_buscar);
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