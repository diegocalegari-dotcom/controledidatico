<?php
require_once __DIR__ . '/../config/database.php';

$conn = connect_db();

echo "Iniciando atualização do schema para v7...\n";

// Passo 1: Remover a coluna 'reserva_tecnica' da tabela 'livros' (se existir)
$sql_drop_column = "ALTER TABLE livros DROP COLUMN reserva_tecnica";
echo "Tentando remover a coluna 'reserva_tecnica' de 'livros'... ";
if ($conn->query($sql_drop_column)) {
    echo "OK\n";
} else {
    // Ignora o erro se a coluna não existir (código de erro 1091)
    if ($conn->errno == 1091) {
        echo "Coluna não existia, nada a fazer.\n";
    } else {
        echo "ERRO: " . $conn->error . "\n";
    }
}

// Passo 2: Criar a tabela 'reserva_tecnica' com a estrutura correta
$sql_create_table = "CREATE TABLE IF NOT EXISTS reserva_tecnica (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    livro_id INT NOT NULL,\n    conservacao ENUM('ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO') NOT NULL,\n    quantidade INT NOT NULL DEFAULT 0,\n    ano_letivo YEAR NOT NULL,\n    UNIQUE KEY idx_reserva_unica (livro_id, conservacao, ano_letivo),\n    FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

echo "Criando a tabela 'reserva_tecnica'... ";
if ($conn->query($sql_create_table)) {
    echo "OK\n";
} else {
    echo "ERRO: " . $conn->error . "\n";
}

echo "Atualização do schema para v7 concluída.\n";

$conn->close();
?>