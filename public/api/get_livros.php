<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$conn = connect_db();

// Opcional: filtrar por série se o id da série for passado
$serie_id = isset($_GET['serie_id']) ? (int)$_GET['serie_id'] : 0;

$sql = "
    SELECT l.id, l.titulo, s.nome as serie_nome
    FROM livros l
    JOIN series s ON l.serie_id = s.id
    WHERE l.status = 'ATIVO'
";

if ($serie_id > 0) {
    $sql .= " AND l.serie_id = ?";
}

$sql .= " ORDER BY s.nome, l.titulo";

$stmt = $conn->prepare($sql);

if ($serie_id > 0) {
    $stmt->bind_param("i", $serie_id);
}

$stmt->execute();
$result = $stmt->get_result();

$livros = [];
while ($row = $result->fetch_assoc()) {
    $livros[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($livros);
?>
