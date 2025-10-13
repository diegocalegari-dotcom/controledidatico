<?php
require_once __DIR__ . '/../config/database.php';

// Inclui o script de criação do DB para garantir um estado limpo
// Este script será executado ao ser incluído
require_once __DIR__ . '/criar_db.php';

$conn = connect_db();
$anny_cgm = '1010429818';

function get_anny_info($conn, $cgm) {
    $stmt = $conn->prepare("SELECT e.nome, e.situacao, t.nome as turma_nome, s.nome as serie_nome, c.nome as curso_nome FROM estudantes e JOIN turmas t ON e.turma_id = t.id JOIN series s ON t.serie_id = s.id JOIN cursos c ON s.curso_id = c.id WHERE e.cgm = ?");
    $stmt->bind_param("s", $cgm);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

echo "\n--- DEBUG ANNY CAROLINE MORAIS SANTOS ---\n";
echo "1. Limpando e recriando o banco de dados (via criar_db.php)...\n";
// criar_db.php já foi executado acima por require_once

echo "2. Arquivando o curso 'PMA-PROGRAMA MAIS APRENDIZAGEM'...\n";
$archive_stmt = $conn->prepare("UPDATE cursos SET status = 'ARQUIVADO' WHERE nome = 'PMA-PROGRAMA MAIS APRENDIZAGEM'");
$archive_stmt->execute();
$archive_stmt->close();
echo "   -> Curso arquivado com sucesso.\n";

echo "3. Importando manha.csv...\n";
$cli_import_path = __DIR__ . "\\cli_import.php";
$manha_csv_path = __DIR__ . "\\..\\manha.csv";
exec("C:\\xampp\\php\\php.exe \"$cli_import_path\" \"$manha_csv_path\"");

echo "4. Verificando Anny após manha.csv:\n";
$anny_info_manha = get_anny_info($conn, $anny_cgm);
if ($anny_info_manha) {
    echo "   -> Turma: {$anny_info_manha['turma_nome']} | Série: {$anny_info_manha['serie_nome']} | Curso: {$anny_info_manha['curso_nome']} | Status: {$anny_info_manha['situacao']}\n";
} else {
    echo "   -> Anny não encontrada no DB após manha.csv.\n";
}

echo "5. Importando tarde.csv...\n";
$tarde_csv_path = __DIR__ . "\\..\\tarde.csv";
exec("C:\\xampp\\php\\php.exe \"$cli_import_path\" \"$tarde_csv_path\"");

echo "6. Verificando Anny após tarde.csv:\n";
$anny_info_tarde = get_anny_info($conn, $anny_cgm);
if ($anny_info_tarde) {
    echo "   -> Turma: {$anny_info_tarde['turma_nome']} | Série: {$anny_info_tarde['serie_nome']} | Curso: {$anny_info_tarde['curso_nome']} | Status: {$anny_info_tarde['situacao']}\n";
} else {
    echo "   -> Anny não encontrada no DB após tarde.csv.\n";
}

$conn->close();
?>