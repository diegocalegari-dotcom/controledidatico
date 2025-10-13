<?php
require_once '../../config/database.php';

header('Content-Type: application/json');
$conn = connect_db();

$ano_letivo = isset($_GET['ano']) ? (int)$_GET['ano'] : 0;

if (!$ano_letivo) {
    http_response_code(400);
    echo json_encode(['error' => 'Ano letivo não especificado.']);
    exit;
}

$sql = "
    SELECT
        s.nome AS serie_nome,
        l.id AS livro_id,
        l.titulo AS livro,
        rt.conservacao AS status,
        rt.quantidade AS total
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

$reservas = [];
while ($row = $result->fetch_assoc()) {
    $serie = $row['serie_nome'];
    if (!isset($reservas[$serie])) {
        $reservas[$serie] = [];
    }
    unset($row['serie_nome']);
    $reservas[$serie][] = $row;
}

$conn->close();

echo json_encode($reservas);
?>
