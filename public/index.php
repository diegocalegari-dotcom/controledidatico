<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

// Busca o ano letivo atualmente ativo
$ano_ativo_q = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo' LIMIT 1");
$ano_letivo_ativo = $ano_ativo_q->fetch_assoc()['valor'] ?? date('Y');

// Busca as turmas do ano letivo ativo
$query = $conn->prepare("
    SELECT 
        t.id as turma_id, 
        t.nome as turma_nome, 
        t.turno, 
        s.nome as serie_nome, 
        c.nome as curso_nome
    FROM turmas t
    JOIN series s ON t.serie_id = s.id
    JOIN cursos c ON s.curso_id = c.id
    WHERE t.ano_letivo = ? AND c.status = 'ATIVO' AND s.status = 'ATIVO'
    ORDER BY c.nome, s.nome, t.nome;
");
$query->bind_param("s", $ano_letivo_ativo);
$query->execute();
$turmas = $query->get_result();

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
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div class="container mt-4">
        <div class="p-5 mb-4 bg-light rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold">Dashboard de Turmas</h1>
                <p class="col-md-8 fs-4">Ano Letivo Ativo: <strong><?php echo $ano_letivo_ativo; ?></strong>. Selecione uma turma para iniciar o controle.</p>
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
                                <a href="controle_turma.php?turma_id=<?php echo $turma['turma_id']; ?>&ano_letivo=<?php echo $ano_letivo_ativo; ?>" class="btn btn-primary">Acessar Controle</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning">Nenhuma turma encontrada para o ano de <?php echo $ano_letivo_ativo; ?>. Você pode importar turmas e alunos na página <a href="importar.php">Importar Alunos</a>.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>