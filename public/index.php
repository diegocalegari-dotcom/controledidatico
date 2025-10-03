<?php
require_once '../config/database.php';
$conn = connect_db();

// Busca todas as turmas ativas, ordenadas por curso, série e nome
$query = "
    SELECT 
        t.id as turma_id, 
        t.nome as turma_nome, 
        t.turno, 
        s.nome as serie_nome, 
        c.nome as curso_nome
    FROM turmas t
    JOIN series s ON t.serie_id = s.id
    JOIN cursos c ON s.curso_id = c.id
    WHERE c.status = 'ATIVO' AND s.status = 'ATIVO'
    ORDER BY c.nome, s.nome, t.nome;
";

$turmas = $conn->query($query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Controle Didático</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
        <div class="p-5 mb-4 bg-light rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold">Dashboard de Turmas</h1>
                <p class="col-md-8 fs-4">Selecione uma turma abaixo para iniciar o controle de entrega e devolução de livros didáticos.</p>
            </div>
        </div>

        <div class="row">
            <?php if ($turmas && $turmas->num_rows > 0): ?>
                <?php while($turma = $turmas->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($turma['serie_nome'] . ' - Turma ' . $turma['turma_nome']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($turma['curso_nome']); ?></h6>
                                <p class="card-text">Turno: <?php echo htmlspecialchars($turma['turno']); ?></p>
                                <a href="controle_turma.php?turma_id=<?php echo $turma['turma_id']; ?>" class="btn btn-primary">Acessar Controle</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning">Nenhuma turma encontrada. Importe os alunos na página <a href="importar.php">Importar Alunos</a>.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>