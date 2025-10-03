<?php
require_once '../config/database.php';
$conn = connect_db();

$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
if ($turma_id <= 0) {
    header("Location: index.php");
    exit;
}

// Busca dados da turma, série e curso
$turma_info_q = $conn->prepare("SELECT t.nome as turma_nome, s.id as serie_id, s.nome as serie_nome, c.nome as curso_nome FROM turmas t JOIN series s ON t.serie_id = s.id JOIN cursos c ON s.curso_id = c.id WHERE t.id = ?");
$turma_info_q->bind_param("i", $turma_id);
$turma_info_q->execute();
$turma_info_r = $turma_info_q->get_result();
if ($turma_info_r->num_rows == 0) {
    header("Location: index.php");
    exit;
}
$turma = $turma_info_r->fetch_assoc();
$serie_id = $turma['serie_id'];

// Busca alunos da turma
$alunos_q = $conn->prepare("SELECT id, nome FROM estudantes WHERE turma_id = ? ORDER BY nome");
$alunos_q->bind_param("i", $turma_id);
$alunos_q->execute();
$alunos = $alunos_q->get_result();

// Busca livros da série
$livros_q = $conn->prepare("SELECT id, titulo FROM livros WHERE serie_id = ? AND status = 'ATIVO' ORDER BY titulo");
$livros_q->bind_param("i", $serie_id);
$livros_q->execute();
$livros = $livros_q->get_result();
$livros_array = $livros->fetch_all(MYSQLI_ASSOC);

// Busca os empréstimos existentes para montar o grid
$emprestimos_q = $conn->query("SELECT id as emprestimo_id, estudante_id, livro_id, status, conservacao_entrega FROM emprestimos");
$emprestimos = [];
while($e = $emprestimos_q->fetch_assoc()) {
    $emprestimos[$e['estudante_id']][$e['livro_id']] = [
        'emprestimo_id' => $e['emprestimo_id'],
        'status' => $e['status'],
        'conservacao' => $e['conservacao_entrega']
    ];
}

// Busca todas as outras turmas para o remanejamento
$outras_turmas_q = $conn->prepare("SELECT t.id, CONCAT(s.nome, ' - ', t.nome) as nome_completo FROM turmas t JOIN series s ON t.serie_id = s.id WHERE t.id != ? ORDER BY nome_completo");
$outras_turmas_q->bind_param("i", $turma_id);
$outras_turmas_q->execute();
$outras_turmas = $outras_turmas_q->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Controle da Turma</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .table-responsive { max-height: 75vh; }
        .table td, .table th { text-align: center; vertical-align: middle; height: 55px; }
        .table th { width: 200px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Controle Didático</a>
            <a class="btn btn-outline-light" href="index.php"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <h3>Controle da Turma: <?php echo htmlspecialchars($turma['serie_nome'] . ' - ' . $turma['turma_nome']); ?></h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adicionarAlunoModal">
                <i class="bi bi-plus-circle"></i> Adicionar Novo Aluno
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-primary" style="position: sticky; top: 0; z-index: 1;">
                    <tr>
                        <th style="width: 50px;"></th>
                        <th style="width: 250px;">Aluno</th>
                        <th style="width: 180px;">Entregar Todos (BOM)</th>
                        <?php foreach ($livros_array as $livro): ?>
                            <th title="<?php echo htmlspecialchars($livro['titulo']); ?>"><?php echo htmlspecialchars($livro['titulo']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while($aluno = $alunos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary btn-gerenciar-aluno" data-aluno-id="<?php echo $aluno['id']; ?>" title="Gerenciar Aluno">
                                    <i class="bi bi-gear"></i>
                                </button>
                            </td>
                            <td class="text-start ps-2"><?php echo htmlspecialchars($aluno['nome']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary btn-entregar-todos" data-aluno-id="<?php echo $aluno['id']; ?>" title="Entregar todos os livros para este aluno">
                                    <i class="bi bi-check-all"></i>
                                </button>
                            </td>
                            <?php foreach ($livros_array as $livro): ?>
                                <td>
                                    <?php 
                                    $emprestimo = $emprestimos[$aluno['id']][$livro['id']] ?? null;
                                    if ($emprestimo) {
                                        switch ($emprestimo['status']) {
                                            case 'Emprestado':
                                                echo '<a href="#" class="badge bg-success text-decoration-none btn-devolver" data-bs-toggle="modal" data-bs-target="#devolucaoModal" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-conservacao-entrega="' . htmlspecialchars($emprestimo['conservacao']) . '"
                                                       ><i class="bi bi-check-circle-fill"></i> ENTREGUE (' . htmlspecialchars($emprestimo['conservacao']) . ')</a>';
                                                break;
                                            case 'Devolvido':
                                                echo '<span class="badge bg-secondary"><i class="bi bi-arrow-return-left"></i> Devolvido</span>';
                                                break;
                                            case 'Perdido':
                                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill"></i> Perdido</span>';
                                                break;
                                        }
                                    } else {
                                        echo '<button class="btn btn-primary btn-sm btn-entregar" data-bs-toggle="modal" data-bs-target="#entregaModal" data-livro-id="' . $livro['id'] . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-aluno-id="' . $aluno['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '"><i class="bi bi-plus-circle"></i> Entregar</button>';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Entrega -->
    <div class="modal fade" id="entregaModal" tabindex="-1" aria-labelledby="entregaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="entregaModalLabel">Registrar Entrega</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-aluno-nome"></span></p>
                    <p><strong>Livro:</strong> <span id="modal-livro-titulo"></span></p>
                    <form id="form-entrega">
                        <input type="hidden" id="modal-livro-id">
                        <input type="hidden" id="modal-aluno-id">
                        <div class="mb-3">
                            <label for="conservacao" class="form-label">Estado de Conservação</label>
                            <select class="form-select" id="conservacao">
                                <option>ÓTIMO</option><option selected>BOM</option><option>REGULAR</option><option>RUIM</option><option>PÉSSIMO</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btn-confirmar-entrega">Confirmar Entrega</button></div>
            </div>
        </div>
    </div>

    <!-- Modal de Devolução -->
    <div class="modal fade" id="devolucaoModal" tabindex="-1" aria-labelledby="devolucaoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="devolucaoModalLabel">Registrar Devolução ou Perda</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-devolucao-aluno-nome"></span></p>
                    <p><strong>Livro:</strong> <span id="modal-devolucao-livro-titulo"></span></p>
                    <p><strong>Conservação na Entrega:</strong> <span id="modal-devolucao-conservacao-entrega"></span></p>
                    <form id="form-devolucao">
                        <input type="hidden" id="modal-emprestimo-id">
                        <div class="mb-3">
                            <label for="conservacao_devolucao" class="form-label">Estado de Conservação (Devolução)</label>
                            <select class="form-select" id="conservacao_devolucao"><option>ÓTIMO</option><option selected>BOM</option><option>REGULAR</option><option>RUIM</option><option>PÉSSIMO</option></select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <button type="button" class="btn btn-danger" id="btn-cancelar-entrega">Cancelar Entrega</button>
                        <button type="button" class="btn btn-warning ms-2" id="btn-marcar-perdido">Marcar como Perdido</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" id="btn-confirmar-devolucao">Confirmar Devolução</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Adicionar Aluno -->
    <div class="modal fade" id="adicionarAlunoModal" tabindex="-1" aria-labelledby="adicionarAlunoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adicionarAlunoModalLabel">Adicionar Novo Aluno à Turma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="form-adicionar-aluno">
                        <div class="mb-3">
                            <label for="novo_aluno_nome" class="form-label">Nome Completo do Aluno</label>
                            <input type="text" class="form-control" id="novo_aluno_nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="novo_aluno_cgm" class="form-label">CGM (Código de Matrícula)</label>
                            <input type="text" class="form-control" id="novo_aluno_cgm" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-salvar-novo-aluno">Salvar Aluno</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Gerenciamento de Aluno -->
    <div class="modal fade" id="gerenciarAlunoModal" tabindex="-1" aria-labelledby="gerenciarAlunoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gerenciarAlunoModalLabel">Gerenciar Aluno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-gerenciar-aluno-nome"></span></p>
                    <form id="form-gerenciar-aluno">
                        <input type="hidden" id="modal-gerenciar-aluno-id">
                        <div class="mb-3">
                            <label for="remanejar_turma_id" class="form-label">Remanejar para outra Turma</label>
                            <select class="form-select" id="remanejar_turma_id">
                                <option value="">Selecione a nova turma...</option>
                                <?php while($t = $outras_turmas->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome_completo']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-danger" id="btn-remover-aluno">Remover da Turma</button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn-salvar-remanejamento">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const entregaModal = new bootstrap.Modal(document.getElementById('entregaModal'));
        const devolucaoModal = new bootstrap.Modal(document.getElementById('devolucaoModal'));
        const gerenciarAlunoModal = new bootstrap.Modal(document.getElementById('gerenciarAlunoModal'));
        const adicionarAlunoModal = new bootstrap.Modal(document.getElementById('adicionarAlunoModal'));
        let currentCell;

        const urlParams = new URLSearchParams(window.location.search);
        const turmaId = urlParams.get('turma_id');

        document.querySelector('tbody').addEventListener('click', function(event) {
            const target = event.target.closest('button, a'); // Apenas botões ou links
            if (!target) return;

            currentCell = target.parentElement;

            // Usa if/else if para garantir que apenas um bloco seja executado
            if (target.classList.contains('btn-entregar')) {
                document.getElementById('modal-aluno-nome').textContent = target.getAttribute('data-aluno-nome');
                document.getElementById('modal-livro-titulo').textContent = target.getAttribute('data-livro-titulo');
                document.getElementById('modal-aluno-id').value = target.getAttribute('data-aluno-id');
                document.getElementById('modal-livro-id').value = target.getAttribute('data-livro-id');
            } else if (target.classList.contains('btn-devolver')) {
                document.getElementById('modal-devolucao-aluno-nome').textContent = target.getAttribute('data-aluno-nome');
                document.getElementById('modal-devolucao-livro-titulo').textContent = target.getAttribute('data-livro-titulo');
                document.getElementById('modal-devolucao-conservacao-entrega').textContent = target.getAttribute('data-conservacao-entrega');
                document.getElementById('modal-emprestimo-id').value = target.getAttribute('data-emprestimo-id');
            } else if (target.classList.contains('btn-gerenciar-aluno')) {
                const alunoId = target.getAttribute('data-aluno-id');
                // Agora o nome do aluno está na segunda TD da TR
                const alunoNome = target.closest('tr').querySelector('td:nth-child(2)').textContent;
                document.getElementById('modal-gerenciar-aluno-nome').textContent = alunoNome;
                document.getElementById('modal-gerenciar-aluno-id').value = alunoId;
                gerenciarAlunoModal.show();
            }
        });

        document.getElementById('btn-confirmar-entrega').addEventListener('click', function() {
            const data = { aluno_id: document.getElementById('modal-aluno-id').value, livro_id: document.getElementById('modal-livro-id').value, conservacao: document.getElementById('conservacao').value };
            fetch('api/registrar_emprestimo.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => { if (result.success) { entregaModal.hide(); location.reload(); } else { alert('Erro: ' + result.message); } }).catch(err => alert('Erro de comunicação.'));
        });

        document.getElementById('btn-cancelar-entrega').addEventListener('click', function() {
            if (!confirm('Tem certeza que deseja CANCELAR esta entrega? A ação não pode ser desfeita.')) return;
            const data = { emprestimo_id: document.getElementById('modal-emprestimo-id').value };
            fetch('api/cancelar_emprestimo.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => { if (result.success) { devolucaoModal.hide(); location.reload(); } else { alert('Erro: ' + result.message); } }).catch(err => alert('Erro de comunicação.'));
        });

        document.getElementById('btn-confirmar-devolucao').addEventListener('click', function() {
            const emprestimo_id = document.getElementById('modal-emprestimo-id').value;
            const conservacao = document.getElementById('conservacao_devolucao').value;
            const data = { emprestimo_id: emprestimo_id, conservacao: conservacao };

            fetch('api/registrar_devolucao.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    devolucaoModal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            }).catch(err => alert('Erro de comunicação.'));
        });

        document.getElementById('btn-marcar-perdido').addEventListener('click', function() {
            if (!confirm('Tem certeza que deseja marcar este livro como PERDIDO?')) return;
            const emprestimo_id = document.getElementById('modal-emprestimo-id').value;
            const data = { emprestimo_id: emprestimo_id };

            fetch('api/marcar_como_perdido.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    devolucaoModal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            }).catch(err => alert('Erro de comunicação.'));
        });

        document.querySelectorAll('.btn-entregar-todos').forEach(button => {
            button.addEventListener('click', function() {
                if (!confirm('Entregar TODOS os livros pendentes para este aluno como \'BOM\'?')) return;
                const data = { aluno_id: this.getAttribute('data-aluno-id'), livros_ids: [] };
                this.closest('tr').querySelectorAll('.btn-entregar').forEach(btn => data.livros_ids.push(btn.getAttribute('data-livro-id')));
                if (data.livros_ids.length === 0) { alert('Este aluno já possui todos os livros.'); return; }
                fetch('api/registrar_entrega_massa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
                .then(res => res.json()).then(result => { if (result.success) { location.reload(); } else { alert('Erro: ' + result.message); } }).catch(err => alert('Erro de comunicação.'));
            });
        });

        document.getElementById('btn-salvar-remanejamento').addEventListener('click', function() {
            const aluno_id = document.getElementById('modal-gerenciar-aluno-id').value;
            const nova_turma_id = document.getElementById('remanejar_turma_id').value;

            if (!nova_turma_id) {
                alert('Por favor, selecione uma turma de destino.');
                return;
            }

            if (!confirm('Tem certeza que deseja remanejar este aluno?')) return;

            const data = { aluno_id: aluno_id, nova_turma_id: nova_turma_id };

            fetch('api/remanejar_aluno.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    gerenciarAlunoModal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            }).catch(err => alert('Erro de comunicação.'));
        });

        document.getElementById('btn-remover-aluno').addEventListener('click', function() {
            const aluno_id = document.getElementById('modal-gerenciar-aluno-id').value;

            if (!confirm('ATENÇÃO: Esta ação irá remover o aluno da turma. Ele não aparecerá mais nesta lista, mas os registros de empréstimos existentes serão mantidos. Deseja continuar?')) return;

            const data = { aluno_id: aluno_id };

            fetch('api/remover_aluno_turma.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    gerenciarAlunoModal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            }).catch(err => alert('Erro de comunicação.'));
        });

        document.getElementById('btn-salvar-novo-aluno').addEventListener('click', function() {
            const novoAlunoNome = document.getElementById('novo_aluno_nome').value;
            const novoAlunoCgm = document.getElementById('novo_aluno_cgm').value;

            if (!novoAlunoNome || !novoAlunoCgm) {
                alert('Por favor, preencha todos os campos para adicionar o aluno.');
                return;
            }

            const data = { nome: novoAlunoNome, cgm: novoAlunoCgm, turma_id: turmaId };

            fetch('api/adicionar_aluno.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    adicionarAlunoModal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            }).catch(err => alert('Erro de comunicação.'));
        });
    });
    </script>
</body>
</html>