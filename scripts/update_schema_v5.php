<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

$sql = "
ALTER TABLE anos_letivos
ADD COLUMN sessao_ativa ENUM('ENTREGA', 'DEVOLUCAO') NOT NULL DEFAULT 'ENTREGA'
";

if ($conn->query($sql) === TRUE) {
    echo "Coluna 'sessao_ativa' adicionada à tabela 'anos_letivos' com sucesso.\n";
} else {
    // Check if the column already exists to avoid error on re-run
    if ($conn->errno == 1060) { // Error number for "Duplicate column name"
        echo "Coluna 'sessao_ativa' já existe na tabela 'anos_letivos'.\n";
    } else {
        echo "Erro ao adicionar coluna 'sessao_ativa': " . $conn->error . "\n";
    }
}

$conn->close();
?>
?>