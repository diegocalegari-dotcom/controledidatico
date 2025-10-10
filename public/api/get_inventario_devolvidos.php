<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$ano_letivo = isset($_GET['ano']) ? (int)$_GET['ano'] : 0;

if (!$ano_letivo) {
    http_response_code(400);
    echo json_encode(['error' => 'Ano letivo não especificado.']);
    exit;
}

$conn = connect_db();

$sql = "
    SELECT
        s.nome AS serie_nome,
        l.id AS livro_id,
        l.titulo AS livro,
        COALESCE(e.conservacao_devolucao, e.status) AS status,
        COUNT(e.id) AS total
    FROM emprestimos e
    JOIN livros l ON e.livro_id = l.id
    JOIN estudantes est ON e.estudante_id = est.id
    JOIN turmas t ON est.turma_id = t.id
    JOIN series s ON t.serie_id = s.id
    WHERE e.ano_letivo = ?
    AND (e.status = 'Devolvido' OR e.status = 'Perdido')
    GROUP BY s.nome, l.id, l.titulo, COALESCE(e.conservacao_devolucao, e.status)
    ORDER BY s.nome, l.titulo, status;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ano_letivo);
$stmt->execute();
$result = $stmt->get_result();

$devolvidos = [];
while ($row = $result->fetch_assoc()) {
    $serie = $row['serie_nome'];
    if (!isset($devolvidos[$serie])) {
        $devolvidos[$serie] = [];
    }
    unset($row['serie_nome']);
    $devolvidos[$serie][] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($devolvidos);
?>