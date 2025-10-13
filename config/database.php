<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Usuário padrão do XAMPP
define('DB_PASS', '');      // Senha padrão do XAMPP é vazia
define('DB_NAME', 'controledidatico');

// Tenta criar uma conexão com o banco de dados
function connect_db() {
    // Habilita exceções para erros do mysqli
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Lança a exceção para ser tratada pelo script que chamou a função
        throw new mysqli_sql_exception("Falha na conexão com o banco de dados: " . $e->getMessage(), $e->getCode());
    }
}
?>