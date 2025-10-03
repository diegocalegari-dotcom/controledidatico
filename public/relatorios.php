<?php
require_once '../config/database.php';
$conn = connect_db();

// Busca anos letivos disponíveis nos empréstimos
$anos_q = $conn->query("SELECT DISTINCT ano_letivo FROM emprestimos ORDER BY ano_letivo DESC");
$anos_disponiveis = [];
while($ano = $anos_q->fetch_assoc()) {
    $anos_disponiveis[] = $ano['ano_letivo'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - Controle Didático</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="gerenciar_materias.php">Matérias</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_cursos.php">Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_series.php">Séries</a></li>
                    <li class="nav-item"><a class="nav-link" href="gerenciar_livros.php">Livros</a></li>
                    <li class="nav-item"><a class="nav-link" href="importar.php">Importar Alunos</a></li>
                    <li class="nav-item"><a class="nav-link" href="configuracoes.php">Configurações</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Relatórios Anuais</h1>
        <p>Selecione o ano letivo e o tipo de relatório para gerar.</p>

        <form id="form-gerar-relatorio" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="anoLetivo" class="form-label">Ano Letivo</label>
                    <select class="form-select" id="anoLetivo" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($anos_disponiveis as $ano): ?>
                            <option value="<?php echo $ano; ?>"><?php echo $ano; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tipoRelatorio" class="form-label">Tipo de Relatório</label>
                    <select class="form-select" id="tipoRelatorio" required>
                        <option value="">Selecione...</option>
                        <option value="pendencias">Pendências de Devolução</option>
                        <option value="qualidade">Qualidade de Devolução</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-arrow-down"></i> Gerar Relatório</button>
                </div>
            </div>
        </form>

        <div id="relatorio-output"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('form-gerar-relatorio').addEventListener('submit', function(event) {
            event.preventDefault();

            const anoLetivo = document.getElementById('anoLetivo').value;
            const tipoRelatorio = document.getElementById('tipoRelatorio').value;

            if (!anoLetivo || !tipoRelatorio) {
                alert('Por favor, selecione o ano letivo e o tipo de relatório.');
                return;
            }

            // Redirecionar para a API que irá gerar e forçar o download do CSV
            window.location.href = `api/gerar_relatorio_anual.php?ano=${anoLetivo}&tipo=${tipoRelatorio}`;
        });
    </script>
</body>
</html>