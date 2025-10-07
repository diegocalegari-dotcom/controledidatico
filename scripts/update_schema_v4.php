<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

// Criar a tabela anos_letivos
$sql_create_table = "
CREATE TABLE IF NOT EXISTS anos_letivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ano INT(4) NOT NULL UNIQUE
);";

if ($conn->query($sql_create_table) === TRUE) {
    echo "Tabela 'anos_letivos' criada ou já existente com sucesso.\n";
} else {
    echo "Erro ao criar tabela 'anos_letivos': " . $conn->error . "\n";
}

// Inserir o ano atual se não existir
$current_year = date('Y');
$sql_insert_current_year = "INSERT IGNORE INTO anos_letivos (ano) VALUES (?)";
$stmt = $conn->prepare($sql_insert_current_year);
$stmt->bind_param("i", $current_year);

if ($stmt->execute() === TRUE) {
    echo "Ano letivo atual ({$current_year}) inserido ou já existente com sucesso.\n";
} else {
    echo "Erro ao inserir ano letivo atual: " . $conn->error . "\n";
}
$stmt->close();

$conn->close();
?>