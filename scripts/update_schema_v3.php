<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

// Adicionar a coluna ano_letivo à tabela emprestimos
$sql_add_column = "ALTER TABLE emprestimos ADD COLUMN ano_letivo INT(4) AFTER data_entrega";
if ($conn->query($sql_add_column) === TRUE) {
    echo "Coluna 'ano_letivo' adicionada à tabela 'emprestimos' com sucesso.\n";
} else {
    echo "Erro ao adicionar coluna 'ano_letivo': " . $conn->error . "\n";
}

// Preencher a coluna ano_letivo com base na data_entrega existente
// Assumindo que o ano letivo é o ano da data de entrega
$sql_update_data = "UPDATE emprestimos SET ano_letivo = YEAR(data_entrega) WHERE ano_letivo IS NULL";
if ($conn->query($sql_update_data) === TRUE) {
    echo "Coluna 'ano_letivo' preenchida com dados existentes com sucesso.\n";
} else {
    echo "Erro ao preencher coluna 'ano_letivo': " . $conn->error . "\n";
}

$conn->close();
?>