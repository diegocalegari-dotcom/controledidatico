<?php
require_once '../../config/database.php';

$ano_letivo = $_GET['ano'] ?? null;
$tipo_relatorio = $_GET['tipo'] ?? null;

if (empty($ano_letivo) || empty($tipo_relatorio)) {
    die("Parâmetros de ano letivo e tipo de relatório são obrigatórios.");
}

$conn = connect_db();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="relatorio_' . $tipo_relatorio . '_' . $ano_letivo . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Ano Letivo', 'Aluno', 'CGM', 'Livro', 'ISBN', 'Status', 'Conservacao Entrega', 'Conservacao Devolucao', 'Data Entrega', 'Data Devolucao']);

$query = "";
$params = [];
$types = "";

if ($tipo_relatorio === 'pendencias') {
    $query = "
        SELECT
            e.ano_letivo,
            s.nome as aluno_nome,
            s.cgm,
            l.titulo as livro_titulo,
            l.isbn,
            e.status,
            e.conservacao_entrega,
            e.conservacao_devolucao,
            e.data_entrega,
            e.data_devolucao
        FROM emprestimos e
        JOIN estudantes s ON e.estudante_id = s.id
        JOIN livros l ON e.livro_id = l.id
        WHERE e.ano_letivo = ? AND e.status NOT IN ('Devolvido', 'Perdido')
        ORDER BY s.nome, l.titulo
    ";
    $params = [$ano_letivo];
    $types = "i";
} elseif ($tipo_relatorio === 'qualidade') {
    $query = "
        SELECT
            e.ano_letivo,
            s.nome as aluno_nome,
            s.cgm,
            l.titulo as livro_titulo,
            l.isbn,
            e.status,
            e.conservacao_entrega,
            e.conservacao_devolucao,
            e.data_entrega,
            e.data_devolucao
        FROM emprestimos e
        JOIN estudantes s ON e.estudante_id = s.id
        JOIN livros l ON e.livro_id = l.id
        WHERE e.ano_letivo = ? AND e.status = 'Devolvido'
        ORDER BY s.nome, l.titulo
    ";
    $params = [$ano_letivo];
    $types = "i";
} else {
    die("Tipo de relatório inválido.");
}

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Erro na preparação da query: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
$stmt->close();
$conn->close();
exit;
?>