<?php
$mensagem = '';
$frase_confirmacao = "tenho certeza que desejo apagar os dados salvos";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirmacao']) && $_POST['confirmacao'] === $frase_confirmacao) {
        
        // Executa o script de inicialização do banco de dados
        // Captura a saída para exibir ao usuário
        ob_start();
        include '../scripts/init_db.php';
        $output = ob_get_clean();

        $mensagem = '<div class="alert alert-success">Banco de dados resetado com sucesso!</div>';
        $mensagem .= '<pre class="alert alert-secondary">' . htmlspecialchars($output) . '</pre>';

    } else {
        $mensagem = '<div class="alert alert-danger">A frase de confirmação não corresponde. Nenhuma ação foi tomada.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Configurações</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="gerenciar_materias.php">Matérias</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_cursos.php">Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_series.php">Séries</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_livros.php">Livros</a></li>
                    <li class="nav-item"><a class="nav-link" href="importar.php">Importar Alunos</a></li>
                    <li class="nav-item"><a class="nav-link active" href="configuracoes.php">Configurações</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Configurações</h2>
        <?php echo $mensagem; ?>

        <div class="card border-danger mt-4">
            <div class="card-header bg-danger text-white">Zona de Perigo</div>
            <div class="card-body">
                <h5 class="card-title">Resetar Banco de Dados</h5>
                <p class="card-text">Esta ação é irreversível. Ela apagará TODAS as tabelas (matérias, cursos, séries, livros, alunos, etc.) e as recriará do zero. Todo o trabalho de importação e cadastro será perdido.</p>
                
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="confirmacao" class="form-label">Para confirmar, digite exatamente a seguinte frase: <br><strong><?php echo $frase_confirmacao; ?></strong></label>
                        <input type="text" class="form-control" id="confirmacao" name="confirmacao" autocomplete="off">
                    </div>
                    <button type="submit" id="resetButton" class="btn btn-danger" disabled>Resetar Banco de Dados</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const confirmInput = document.getElementById('confirmacao');
        const resetButton = document.getElementById('resetButton');
        const frase = '<?php echo $frase_confirmacao; ?>';

        confirmInput.addEventListener('keyup', function() {
            if (this.value === frase) {
                resetButton.disabled = false;
            } else {
                resetButton.disabled = true;
            }
        });
    </script>
</body>
</html>