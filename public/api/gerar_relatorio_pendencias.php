<?php
require_once __DIR__ . '/../../config/database.php';

$ano_letivo = isset($_GET['ano_letivo']) ? (int)$_GET['ano_letivo'] : date('Y');

$conn = connect_db();

// Consulta para buscar alunos com pendências no ano letivo especificado
$query = $conn->prepare("
    SELECT 
        e.nome AS estudante_nome,
        e.cgm,
        l.titulo AS livro_titulo,
        l.isbn,
        em.data_entrega,
        c.nome AS curso_nome,
        s.nome AS serie_nome,
        t.nome AS turma_nome
    FROM emprestimos em
    JOIN estudantes e ON em.estudante_id = e.id
    JOIN livros l ON em.livro_id = l.id
    JOIN turmas t ON e.turma_id = t.id
    JOIN series s ON t.serie_id = s.id
    JOIN cursos c ON s.curso_id = c.id
    WHERE em.status = 'Emprestado' AND em.ano_letivo = ?
    ORDER BY c.nome, s.nome, t.nome, e.nome ASC;
");
$query->bind_param("s", $ano_letivo);
$query->execute();
$result = $query->get_result();

// Define os cabeçalhos para forçar o download do arquivo
$filename = "relatorio_pendencias_" . $ano_letivo . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Abre o ponteiro para a saída padrão (o navegador)
$output = fopen('php://output', 'w');

// Adiciona o BOM para garantir a codificação UTF-8 correta no Excel
fputs($output, "\xEF\xBB\xBF");

// Escreve o cabeçalho do CSV
fputcsv($output, [
    'Curso',
    'Série',
    'Turma',
    'Nome do Aluno',
    'CGM',
    'Título do Livro',
    'ISBN',
    'Data da Entrega'
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
            date("d/m/Y", strtotime($row['data_entrega']))
        ]);
    }
}

fclose($output);
$conn->close();
exit();
?>