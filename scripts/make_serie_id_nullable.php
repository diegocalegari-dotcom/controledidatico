<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

$sql = "ALTER TABLE turmas MODIFY COLUMN serie_id INT NULL;";

if ($conn->query($sql) === TRUE) {
    echo "Coluna 'serie_id' na tabela 'turmas' alterada para permitir valores NULL com sucesso.\n";
} else {
    echo "Erro ao alterar coluna: " . $conn->error . "\n";
}

$conn->close();
?>