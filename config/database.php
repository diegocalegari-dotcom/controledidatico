<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Usuário padrão do XAMPP
define('DB_PASS', '');      // Senha padrão do XAMPP é vazia
define('DB_NAME', 'controledidatico');

// Tenta criar uma conexão com o banco de dados
function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Checa a conexão
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Garante que o charset é UTF-8
    $conn->set_charset("utf8mb4");

    return $conn;
}
?>