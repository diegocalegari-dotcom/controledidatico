<?php
require_once __DIR__ . '/../../config/database.php';

// Define o ano letivo para o relatório. No futuro, isso pode vir de um seletor na interface.
$ano_letivo = isset($_GET['ano_letivo']) ? (int)$_GET['ano_letivo'] : date('Y');

$conn = connect_db();

// Query para buscar todos os registros de empréstimo do ano, com todos os detalhes
$query = $conn->prepare("
    SELECT 
        e.nome AS estudante_nome,
        e.cgm,
        c.nome AS curso_nome,
        s.nome AS serie_nome,
        t.nome AS turma_nome,
        l.titulo AS livro_titulo,
        l.isbn,
        em.status,
        em.data_entrega,
        em.conservacao_entrega,
        em.data_devolucao,
        em.conservacao_devolucao
    FROM emprestimos em
    JOIN estudantes e ON em.estudante_id = e.id
    JOIN livros l ON em.livro_id = l.id
    LEFT JOIN turmas t ON e.turma_id = t.id
    LEFT JOIN series s ON t.serie_id = s.id
    LEFT JOIN cursos c ON s.curso_id = c.id
    WHERE em.ano_letivo = ?
    ORDER BY c.nome, s.nome, t.nome, e.nome ASC;
");
$query->bind_param("s", $ano_letivo);
$query->execute();
$result = $query->get_result();

// Define os cabeçalhos para forçar o download do arquivo
$filename = "relatorio_final_" . $ano_letivo . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Adiciona o BOM para garantir a codificação UTF-8 correta no Excel
fputs($output, "\xEF\xBB\xBF");

// Escreve o cabeçalho do CSV
fputcsv($output, [
    'Curso',
    'Série',
    'Turma',
    'Aluno',
    'CGM',
    'Livro',
    'ISBN',
    'Status Final',
    'Data Entrega',
    'Conservação Entrega',
    'Data Devolução',
    'Conservação Devolução'
]);

// Escreve os dados no CSV
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['curso_nome'],
            $row['serie_nome'],
            $row['turma_nome'],
            $row['estudante_nome'],
            $row['cgm'],
            $row['livro_titulo'],
            $row['isbn'],
            $row['status'],
            $row['data_entrega'] ? date("d/m/Y", strtotime($row['data_entrega'])) : '',
            $row['conservacao_entrega'],
            $row['data_devolucao'] ? date("d/m/Y", strtotime($row['data_devolucao'])) : '',
            $row['conservacao_devolucao']
        ]);
    }
}

fclose($output);
$conn->close();
exit();
?>