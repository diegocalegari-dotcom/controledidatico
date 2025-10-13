<?php
require_once __DIR__ . '/../config/database.php';

// Adiciona a coluna reserva_tecnica na tabela livros

$conn = connect_db();

$sql = "ALTER TABLE livros ADD COLUMN reserva_tecnica INT NOT NULL DEFAULT 0 AFTER status";

if ($conn->query($sql) === TRUE) {
    echo "Tabela 'livros' atualizada com sucesso! Coluna 'reserva_tecnica' adicionada.\n";
} else {
    echo "Erro ao atualizar a tabela 'livros': " . $conn->error . "\n";
}

$conn->close();
?>