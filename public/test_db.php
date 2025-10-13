<?php
// Ativar exibição de todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Iniciando teste de conexão com o banco de dados...<br>";

$host = '127.0.0.1';
$user = 'root';
$pass = ''; // Senha padrão do XAMPP é vazia

// Definir um tempo limite para a conexão
$timeout = 5; // 5 segundos

$link = mysqli_init();
if (!$link) {
    die('mysqli_init falhou');
}

if (!mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, $timeout)) {
    die('mysqli_options falhou');
}

if (!mysqli_real_connect($link, $host, $user, $pass)) {
    die('Falha na conexão (' . mysqli_connect_errno() . '): ' . mysqli_connect_error());
}

echo "Conexão bem-sucedida!";

mysqli_close($link);
?>