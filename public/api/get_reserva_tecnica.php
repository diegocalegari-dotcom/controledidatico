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
        rt.conservacao AS status,
        rt.quantidade
    FROM reserva_tecnica rt
    JOIN livros l ON rt.livro_id = l.id
    JOIN series s ON l.serie_id = s.id
    WHERE rt.ano_letivo = ?
    ORDER BY s.nome, l.titulo, rt.conservacao;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ano_letivo);
$stmt->execute();
$result = $stmt->get_result();

$reserva = [];
while ($row = $result->fetch_assoc()) {
    $serie = $row['serie_nome'];
    if (!isset($reserva[$serie])) {
        $reserva[$serie] = [];
    }
    unset($row['serie_nome']);
    $reserva[$serie][] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($reserva);
?>