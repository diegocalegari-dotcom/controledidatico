<?php
require_once __DIR__ . '/../config/database.php';
$conn = connect_db();

$sql = "ALTER TABLE turmas ADD COLUMN status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'";

if ($conn->query($sql)) {
    echo "Coluna 'status' adicionada à tabela 'turmas' com sucesso.\n";
} else {
    if ($conn->errno == 1060) { // Error 1060: Duplicate column name
        echo "Coluna 'status' já existe na tabela 'turmas'.\n";
    } else {
        echo "Erro ao adicionar coluna 'status': " . $conn->error . "\n";
    }
}

$conn->close();
?>