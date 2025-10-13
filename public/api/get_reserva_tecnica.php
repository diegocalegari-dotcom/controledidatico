<?php
require_once '../../config/database.php';

header('Content-Type: application/json');
$conn = connect_db();

$ano_letivo = date('Y'); // Or get from a parameter if needed later

// 1. Get all active books with their series and course names
$livros_sql = "SELECT l.id, l.titulo, s.nome as serie_nome, c.nome as curso_nome
               FROM livros l
               JOIN series s ON l.serie_id = s.id
               JOIN cursos c ON s.curso_id = c.id
               WHERE l.status = 'ATIVO'
               ORDER BY c.nome, s.nome, l.titulo";

$livros_result = $conn->query($livros_sql);
$livros = [];
while ($row = $livros_result->fetch_assoc()) {
    // Initialize reserves for each book
    $row['reservas'] = [
        'ÓTIMO' => 0,
        'BOM' => 0,
        'REGULAR' => 0,
        'RUIM' => 0,
        'PÉSSIMO' => 0
    ];
    $livros[$row['id']] = $row;
}

// 2. Get the technical reserve counts for the current year
$reserva_sql = "SELECT livro_id, conservacao, quantidade
                FROM reserva_tecnica
                WHERE ano_letivo = ?";
$stmt = $conn->prepare($reserva_sql);
$stmt->bind_param("s", $ano_letivo);
$stmt->execute();
$reserva_result = $stmt->get_result();

while ($row = $reserva_result->fetch_assoc()) {
    if (isset($livros[$row['livro_id']])) {
        $livros[$row['livro_id']]['reservas'][$row['conservacao']] = (int)$row['quantidade'];
    }
}

$conn->close();

// Return as a flat array for the client
echo json_encode(['success' => true, 'data' => array_values($livros)]);
?>
