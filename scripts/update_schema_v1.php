<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

echo "Iniciando atualização do banco de dados...\n";

$queries = [
    "ALTER TABLE cursos ADD COLUMN status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'",
    "ALTER TABLE series ADD COLUMN status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'",
    "ALTER TABLE materias ADD COLUMN status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'"
];

$success = true;
foreach ($queries as $query) {
    echo "Executando: $query ... ";
    if ($conn->query($query)) {
        echo "OK\n";
    } else {
        echo "ERRO: " . $conn->error . "\n";
        $success = false;
        break;
    }
}

if ($success) {
    echo "\nBanco de dados atualizado com sucesso!\n";
} else {
    echo "\nOcorreu um erro ao atualizar o banco de dados.\n";
}

$conn->close();
?>