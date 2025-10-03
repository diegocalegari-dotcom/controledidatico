<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

$aluno_id = $_GET['aluno_id'] ?? null;
$ano_atual = $_GET['ano_atual'] ?? null;

if (empty($aluno_id) || empty($ano_atual)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do aluno e ano atual são obrigatórios.']);
    exit;
}

$conn = connect_db();

// Consulta para pendências de entrega
$query_pendencias = "
    SELECT
        COUNT(e.id) as total_pendencias
    FROM emprestimos e
    WHERE e.estudante_id = ?
    AND e.ano_letivo < ?
    AND e.status NOT IN ('Devolvido', 'Perdido')
";

$stmt_pendencias = $conn->prepare($query_pendencias);
if ($stmt_pendencias === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query de pendências: ' . $conn->error]);
    exit;
}
$stmt_pendencias->bind_param("ii", $aluno_id, $ano_atual);
$stmt_pendencias->execute();
$result_pendencias = $stmt_pendencias->get_result();
$row_pendencias = $result_pendencias->fetch_assoc();
$tem_pendencias = ($row_pendencias['total_pendencias'] > 0);
$total_pendencias = $row_pendencias['total_pendencias'];
$stmt_pendencias->close();

// Consulta para qualidade de devolução ruim
$query_qualidade_ruim = "
    SELECT
        COUNT(e.id) as total_qualidade_ruim
    FROM emprestimos e
    WHERE e.estudante_id = ?
    AND e.ano_letivo < ?
    AND e.status = 'Devolvido'
    AND e.conservacao_devolucao IN ('RUIM', 'PÉSSIMO')
";

$stmt_qualidade_ruim = $conn->prepare($query_qualidade_ruim);
if ($stmt_qualidade_ruim === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query de qualidade ruim: ' . $conn->error]);
    exit;
}
$stmt_qualidade_ruim->bind_param("ii", $aluno_id, $ano_atual);
$stmt_qualidade_ruim->execute();
$result_qualidade_ruim = $stmt_qualidade_ruim->get_result();
$row_qualidade_ruim = $result_qualidade_ruim->fetch_assoc();
$tem_qualidade_ruim = ($row_qualidade_ruim['total_qualidade_ruim'] > 0);
$total_qualidade_ruim = $row_qualidade_ruim['total_qualidade_ruim'];
$stmt_qualidade_ruim->close();


echo json_encode([
    'success' => true,
    'tem_pendencias' => $tem_pendencias,
    'total_pendencias' => $total_pendencias,
    'tem_qualidade_ruim' => $tem_qualidade_ruim,
    'total_qualidade_ruim' => $total_qualidade_ruim
]);

$conn->close();
?>