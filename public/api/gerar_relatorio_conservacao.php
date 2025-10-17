<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connect_db();

// Obter o ano letivo ativo
$ano_letivo_ativo = null;
$result = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo'");
if ($row = $result->fetch_assoc()) {
    $ano_letivo_ativo = $row['valor'];
}

if (!$ano_letivo_ativo) {
    http_response_code(500);
    echo json_encode(['error' => 'Ano letivo ativo não definido.']);
    exit;
}

// Helper function to process query results
function process_results($stmt) {
    $report_data = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $serie_nome = $row['serie_nome'];
        $conservacao = $row['conservacao'];
        $total = (int)$row['total'];

        if (empty($serie_nome) || empty($conservacao)) continue;

        if (!isset($report_data[$serie_nome])) {
            $report_data[$serie_nome] = [];
        }
        $report_data[$serie_nome][$conservacao] = $total;
    }
    $stmt->close();
    return $report_data;
}

$final_report = [];

// 1. Reserva Técnica
$sql_reserva = "
    SELECT s.nome AS serie_nome, rt.conservacao, SUM(rt.quantidade) AS total
    FROM reserva_tecnica rt
    JOIN livros l ON rt.livro_id = l.id
    JOIN series s ON l.serie_id = s.id
    WHERE rt.ano_letivo = ? AND s.status = 'ATIVO'
    GROUP BY s.nome, rt.conservacao
    ORDER BY s.nome, rt.conservacao;
";
$stmt_reserva = $conn->prepare($sql_reserva);
$stmt_reserva->bind_param("i", $ano_letivo_ativo);
$stmt_reserva->execute();
$final_report['reserva_tecnica'] = process_results($stmt_reserva);

// 2. Em Circulação (baseado na conservação de entrega)
$sql_circulacao = "
    SELECT s.nome AS serie_nome, e.conservacao_entrega AS conservacao, COUNT(e.id) AS total
    FROM emprestimos e
    JOIN livros l ON e.livro_id = l.id
    JOIN series s ON l.serie_id = s.id
    WHERE e.ano_letivo = ? AND e.status = 'Emprestado' AND s.status = 'ATIVO'
    GROUP BY s.nome, e.conservacao_entrega
    ORDER BY s.nome, e.conservacao_entrega;
";
$stmt_circulacao = $conn->prepare($sql_circulacao);
$stmt_circulacao->bind_param("i", $ano_letivo_ativo);
$stmt_circulacao->execute();
$final_report['em_circulacao'] = process_results($stmt_circulacao);

// 3. Devolvidos no Ano (baseado na conservação de devolução)
$sql_devolvidos = "
    SELECT s.nome AS serie_nome, e.conservacao_devolucao AS conservacao, COUNT(e.id) AS total
    FROM emprestimos e
    JOIN livros l ON e.livro_id = l.id
    JOIN series s ON l.serie_id = s.id
    WHERE e.ano_letivo = ? AND e.status = 'Devolvido' AND e.conservacao_devolucao IS NOT NULL AND s.status = 'ATIVO'
    GROUP BY s.nome, e.conservacao_devolucao
    ORDER BY s.nome, e.conservacao_devolucao;
";
$stmt_devolvidos = $conn->prepare($sql_devolvidos);
$stmt_devolvidos->bind_param("i", $ano_letivo_ativo);
$stmt_devolvidos->execute();
$final_report['devolvidos'] = process_results($stmt_devolvidos);

$conn->close();

echo json_encode($final_report);
?>
