<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

echo "Iniciando atualização do banco de dados (v2)...\n";

$query = "ALTER TABLE livros ADD COLUMN status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'";

echo "Executando: $query ... ";
if ($conn->query($query)) {
    echo "OK\n";
    echo "\nBanco de dados atualizado com sucesso!\n";
} else {
    echo "ERRO: " . $conn->error . "\n";
}

$conn->close();
?>