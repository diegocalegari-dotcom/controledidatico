<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

$aluno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($aluno_id <= 0) {
    header("Location: index.php"); // Redirect if no valid student ID
    exit;
}

// Fetch active school year
$ano_ativo_q = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo' LIMIT 1");
$ano_letivo_ativo = $ano_ativo_q->fetch_assoc()['valor'] ?? date('Y');

// Fetch student details
$aluno_q = $conn->prepare("SELECT e.id, e.cgm, e.nome, t.id as turma_id, t.nome as turma_nome, s.id as serie_id, s.nome as serie_nome, c.nome as curso_nome FROM estudantes e JOIN turmas t ON e.turma_id = t.id JOIN series s ON t.serie_id = s.id JOIN cursos c ON s.curso_id = c.id WHERE e.id = ?");
$aluno_q->bind_param("i", $aluno_id);
$aluno_q->execute();
$aluno_result = $aluno_q->get_result();
if ($aluno_result->num_rows == 0) {
    header("Location: index.php"); // Redirect if student not found
    exit;
}
$aluno = $aluno_result->fetch_assoc();
$turma_id = $aluno['turma_id'];
$serie_id = $aluno['serie_id']; // Assuming serie_id is available from the join or can be fetched

// Fetch books for the student's series
$livros_q = $conn->prepare("SELECT id, titulo FROM livros WHERE serie_id = ? AND status = 'ATIVO' ORDER BY titulo");
$livros_q->bind_param("i", $serie_id);
$livros_q->execute();
$livros = $livros_q->get_result();
$livros_array = $livros->fetch_all(MYSQLI_ASSOC);

// Fetch current loans for the student for the active year
$emprestimos_q = $conn->prepare("SELECT id as emprestimo_id, estudante_id, livro_id, status, conservacao_entrega, conservacao_devolucao, dado_como_perdido FROM emprestimos WHERE estudante_id = ? AND ano_letivo = ?");
$emprestimos_q->bind_param("is", $aluno_id, $ano_letivo_ativo);
$emprestimos_q->execute();
$emprestimos_r = $emprestimos_q->get_result();
$emprestimos = [];
while($e = $emprestimos_r->fetch_assoc()) {
    $emprestimos[$e['livro_id']] = [
        'emprestimo_id' => $e['emprestimo_id'],
        'status' => $e['status'],
        'conservacao' => $e['conservacao_entrega'],
        'conservacao_devolucao' => $e['conservacao_devolucao'],
        'dado_como_perdido' => $e['dado_como_perdido']
    ];
}

$conn->close();

// Helper function for conservation class (replicated from controle_turma.php)
function get_conservacao_class($conservacao) {
    switch (strtoupper($conservacao)) {
        case 'ÓTIMO':
        case 'BOM':
            return 'bg-bom';
        case 'REGULAR':
            return 'bg-regular';
        case 'RUIM':
            return 'bg-ruim';
        case 'PÉSSIMO':
            return 'bg-pessimo';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Aluno: <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-responsive { max-height: 75vh; }
        .table td, .table th { text-align: center; vertical-align: middle; height: 55px; }
        .table th { width: 200px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body data-aluno-id="<?php echo $aluno_id; ?>" data-ano-atual="<?php echo $ano_letivo_ativo; ?>">
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Aluno: <?php echo htmlspecialchars($aluno['nome']); ?> (CGM: <?php echo htmlspecialchars($aluno['cgm']); ?>)</h3>
            <a href="controle_turma.php?turma_id=<?php echo $turma_id; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Voltar para Turma
            </a>
        </div>

        <p><strong>Turma:</strong> <?php echo htmlspecialchars($aluno['serie_nome'] . ' - ' . $aluno['turma_nome'] . ' (' . $aluno['curso_nome'] . ')'); ?></p>
        <p><strong>Ano Letivo Ativo:</strong> <?php echo $ano_letivo_ativo; ?></p>

        

        <!-- Entregas/Devoluções Section -->
        <div class="mt-4">
            <h4>Livros para <?php echo htmlspecialchars($aluno['nome']); ?> (Ano Letivo <?php echo $ano_letivo_ativo; ?>)</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Livro</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php if (!empty($livros_array)): foreach ($livros_array as $livro): ?>
                                <tr>
                                    <td class="text-start"><?php echo htmlspecialchars($livro['titulo']); ?></td>
                                    <td>
                                        <?php
                                        $emprestimo = $emprestimos[$livro['id']] ?? null;
                                        if ($emprestimo) {
                                            switch ($emprestimo['status']) {
                                                case 'Emprestado':
                                                    $conservacao = htmlspecialchars($emprestimo['conservacao']);
                                                    $classe_cor = get_conservacao_class($conservacao);
                                                    $perdido_icon = '';
                                                    if (!empty($emprestimo['dado_como_perdido'])) {
                                                        $perdido_icon = '<i class="bi bi-exclamation-triangle-fill text-warning me-1" title="Este livro foi marcado como perdido"></i>';
                                                    }
                                                    echo $perdido_icon . '<a href="#" class="badge ' . $classe_cor . ' text-decoration-none btn-devolver" data-bs-toggle="modal" data-bs-target="#devolucaoModal" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-id="' . $aluno['id'] . '" data-livro-id="' . $livro['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-conservacao-entrega="' . $conservacao . '"
                                                           ><i class="bi bi-check-circle-fill"></i> ENTREGUE (' . $conservacao . ')</a>';
                                                    break;
                                                case 'Devolvido':
                                                    $conservacao_devolucao = htmlspecialchars($emprestimo['conservacao_devolucao']);
                                                    $classe_cor = get_conservacao_class($conservacao_devolucao);
                                                    echo '<a class="badge ' . $classe_cor . ' text-decoration-none btn-reverter-devolucao" title="Reverter Devolução" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-id="' . $aluno['id'] . '" data-livro-id="' . $livro['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-revert-conservacao="' . $emprestimo['conservacao'] . '"><i class="bi bi-arrow-counterclockwise"></i> DEVOLVIDO (' . $conservacao_devolucao . ')</a>';
                                                    break;
                                                case 'Perdido':
                                                    echo '<a href="#" class="badge bg-danger text-decoration-none btn-reverter-perda" title="Reverter Status de Perda" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-id="' . $aluno['id'] . '" data-livro-id="' . $livro['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '"><i class="bi bi-arrow-counterclockwise"></i> PERDIDO</a>';
                                                    break;
                                            }
                                        } else {
                                            echo '<button class="btn btn-primary btn-sm btn-entregar" data-bs-toggle="modal" data-bs-target="#entregaModal" data-livro-id="' . $livro['id'] . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-aluno-id="' . $aluno['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '"><i class="bi bi-plus-circle"></i> Entregar</button>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="2" class="text-center">Nenhum livro encontrado para a série deste aluno.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Histórico Completo Section -->
            <div class="mt-4">
                <h4>Histórico de Empréstimos de <?php echo htmlspecialchars($aluno['nome']); ?></h4>
                <div id="historico-aluno-content" class="mt-3">
                    <p class="text-muted">Carregando...</p>
                </div>
            </div>
    </div>

    <!-- Modals (Entrega, Devolução, Perda) - Replicados de controle_turma.php -->
    <!-- Modal de Entrega -->
    <div class="modal fade" id="entregaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Registrar Entrega</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-aluno-nome"></span></p>
                    <p><strong>Livro:</strong> <span id="modal-livro-titulo"></span></p>
                    <input type="hidden" id="modal-livro-id">
                    <input type="hidden" id="modal-aluno-id">
                    <div class="mb-3">
                        <label for="conservacao" class="form-label">Estado de Conservação</label>
                        <select class="form-select form-select-colored" id="conservacao" onchange="updateSelectColor(this)"><option>ÓTIMO</option><option selected>BOM</option><option>REGULAR</option><option>RUIM</option><option>PÉSSIMO</option></select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btn-confirmar-entrega">Confirmar Entrega</button></div>
            </div>
        </div>
    </div>

    <!-- Modal de Devolução -->
    <div class="modal fade" id="devolucaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Registrar Devolução ou Perda</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-devolucao-aluno-nome"></span></p>
                    <p><strong>Livro:</strong> <span id="modal-devolucao-livro-titulo"></span></p>
                    <p><strong>Conservação na Entrega:</strong> <span id="modal-devolucao-conservacao-entrega" class="badge"></span></p>
                    <input type="hidden" id="modal-emprestimo-id">
                    <input type="hidden" id="modal-devolucao-aluno-id">
                    <input type="hidden" id="modal-devolucao-livro-id">
                    <div class="mb-3 mt-3">
                        <label for="conservacao_devolucao" class="form-label">Estado de Conservação (Devolução)</label>
                        <select class="form-select form-select-colored" id="conservacao_devolucao" onchange="updateSelectColor(this)"><option>ÓTIMO</option><option selected>BOM</option><option>REGULAR</option><option>RUIM</option><option>PÉSSIMO</option></select>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <button type="button" class="btn btn-danger" id="btn-cancelar-entrega">(C) Cancelar Entrega</button>
                        <button type="button" class="btn btn-warning ms-2 btn-abrir-modal-perda" data-bs-toggle="modal" data-bs-target="#perdaModal">(M) Marcar como Perdido</button>
                    </div>
                    <div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button><button type="button" class="btn btn-primary" id="btn-confirmar-devolucao">(D) Confirmar Devolução</button></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Marcar como Perdido -->
    <div class="modal fade" id="perdaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Marcar Livro como Perdido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal-perda-emprestimo-id">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="realizar_reposicao" checked>
                        <label class="form-check-label" for="realizar_reposicao">
                            Repor este livro da reserva técnica?
                        </label>
                    </div>
                    <div class="mb-3" id="reposicao_conservacao_div">
                        <label for="conservacao_reposicao" class="form-label">Estado de Conservação da Reposição</label>
                        <p class="form-text text-muted mt-0" style="font-size: 0.875em;">Selecione o estado do livro que será retirado da reserva para substituir o perdido.</p>
                        <select class="form-select" id="conservacao_reposicao">
                            <option>ÓTIMO</option>
                            <option selected>BOM</option>
                            <option>REGULAR</option>
                            <option>RUIM</option>
                            <option>PÉSSIMO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btn-confirmar-perda">Confirmar Perda</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Função para mapear string de conservação para classe de cor
    function getConservacaoClass(conservacao) {
        if (!conservacao) return 'bg-secondary';
        switch (conservacao.toUpperCase()) {
            case 'ÓTIMO':
            case 'BOM':
                return 'bg-bom';
            case 'REGULAR':
                return 'bg-regular';
            case 'RUIM':
                return 'bg-ruim';
            case 'PÉSSIMO':
                return 'bg-pessimo';
            default:
                return 'bg-secondary';
        }
    }

    // Função para atualizar a cor de fundo de um elemento select
    function updateSelectColor(selectElement) {
        selectElement.classList.remove('bg-bom', 'bg-regular', 'bg-ruim', 'bg-pessimo', 'bg-secondary');
        selectElement.classList.add(getConservacaoClass(selectElement.value));
    }
    
    function showToast(message, success = true) {
        const toastContainer = document.getElementById('toast-container');
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${success ? 'bg-success' : 'bg-danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
        toast.show();
        toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('aluno.php script started');
        const entregaModalEl = document.getElementById('entregaModal');
        const entregaModal = new bootstrap.Modal(entregaModalEl);
        const devolucaoModalEl = document.getElementById('devolucaoModal');
        const devolucaoModal = new bootstrap.Modal(devolucaoModalEl);
        const perdaModalEl = document.getElementById('perdaModal');
        const perdaModal = new bootstrap.Modal(perdaModalEl);

        const reposicaoCheckbox = document.getElementById('realizar_reposicao');
        const reposicaoConservacaoDiv = document.getElementById('reposicao_conservacao_div');

        reposicaoCheckbox.addEventListener('change', function() {
            reposicaoConservacaoDiv.style.display = this.checked ? 'block' : 'none';
        });

        perdaModalEl.addEventListener('shown.bs.modal', function () {
            reposicaoConservacaoDiv.style.display = reposicaoCheckbox.checked ? 'block' : 'none';
        });

        const alunoId = document.body.dataset.alunoId;
        const anoLetivo = document.body.dataset.anoAtual;

        // Inicializa a cor dos seletores quando o modal é aberto
        document.getElementById('entregaModal').addEventListener('shown.bs.modal', function () {
            updateSelectColor(document.getElementById('conservacao'));
        });
        document.getElementById('devolucaoModal').addEventListener('shown.bs.modal', function () {
            updateSelectColor(document.getElementById('conservacao_devolucao'));
        });

        // --- Funções Helper ---
        function handleResponse(response) {
            return response.json().then(json => {
                if (!response.ok) {
                    const error = (json && json.message) || response.statusText;
                    return Promise.reject(new Error(error));
                }
                return json;
            });
        }

        function handleSuccess(result, reload = false) {
            if (result.success) {
                showToast(result.message, true);
                if (reload) {
                    setTimeout(() => location.reload(), 1000);
                }
            }
            else {
                showToast(result.message, false);
            }
        }

        function handleError(error) {
            console.error('Erro na operação:', error);
            showToast(error.message, false);
        }

        // --- Event Listeners para Entregas/Devoluções ---
        document.querySelectorAll('.btn-entregar').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('modal-aluno-nome').textContent = this.dataset.alunoNome;
                document.getElementById('modal-livro-titulo').textContent = this.dataset.livroTitulo;
                document.getElementById('modal-aluno-id').value = this.dataset.alunoId;
                document.getElementById('modal-livro-id').value = this.dataset.livroId;
                entregaModal.show();
            });
        });

        document.querySelectorAll('.btn-devolver').forEach(button => {
            button.addEventListener('click', function() {
                const conservacaoEntrega = this.dataset.conservacaoEntrega;
                const conservacaoSpan = document.getElementById('modal-devolucao-conservacao-entrega');
                
                document.getElementById('modal-devolucao-aluno-nome').textContent = this.dataset.alunoNome;
                document.getElementById('modal-devolucao-livro-titulo').textContent = this.dataset.livroTitulo;
                document.getElementById('modal-emprestimo-id').value = this.dataset.emprestimoId;
                document.getElementById('modal-devolucao-aluno-id').value = this.dataset.alunoId;
                document.getElementById('modal-devolucao-livro-id').value = this.dataset.livroId;
                
                conservacaoSpan.textContent = conservacaoEntrega;
                conservacaoSpan.className = 'badge '; // Limpa classes antigas
                conservacaoSpan.classList.add(getConservacaoClass(conservacaoEntrega));
                
                devolucaoModal.show();
            });
        });

        document.querySelectorAll('.btn-reverter-devolucao').forEach(button => {
            button.addEventListener('click', function() {
                const emprestimoId = this.dataset.emprestimoId;
                const alunoNome = this.dataset.alunoNome;
                const livroTitulo = this.dataset.livroTitulo;
                const alunoId = this.dataset.alunoId;
                const livroId = this.dataset.livroId;

                if (!confirm(`Deseja reverter a devolução do livro "${livroTitulo}" para o aluno ${alunoNome}?`)) return;
                
                fetch('api/reverter_devolucao.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ emprestimo_id: emprestimoId }) })
                .then(handleResponse)
                .then(result => {
                    if (result.success && result.reverted_loan) {
                        const loan = result.reverted_loan;
                        const cell = document.querySelector(`td[data-livro-id="${livroId}"]`);

                        if (cell) {
                            const conservacao = loan.conservacao_entrega;
                            const newBadge = document.createElement('a');

                            newBadge.href = '#';
                            newBadge.className = `badge ${getConservacaoClass(conservacao)} text-decoration-none btn-devolver`;
                            newBadge.innerHTML = `<i class="bi bi-check-circle-fill"></i> ENTREGUE (${conservacao})`;
                            
                            newBadge.dataset.bsToggle = 'modal';
                            newBadge.dataset.bsTarget = '#devolucaoModal';
                            newBadge.dataset.emprestimoId = loan.emprestimo_id;
                            newBadge.dataset.alunoId = loan.aluno_id;
                            newBadge.dataset.livroId = loan.livro_id;
                            newBadge.dataset.alunoNome = alunoNome;
                            newBadge.dataset.livroTitulo = livroTitulo;
                            newBadge.dataset.conservacaoEntrega = conservacao;

                            cell.innerHTML = '';
                            cell.appendChild(newBadge);
                        }
                        handleSuccess(result, false);
                    } else {
                        handleError(new Error(result.message || 'Não foi possível reverter a devolução.'));
                    }
                })
                .catch(handleError);
            });
        });

        document.querySelectorAll('.btn-reverter-perda').forEach(button => {
            button.addEventListener('click', function() {
                const emprestimoId = this.dataset.emprestimoId;
                const alunoNome = this.dataset.alunoNome;
                const livroTitulo = this.dataset.livroTitulo;
                const alunoId = this.dataset.alunoId;
                const livroId = this.dataset.livroId;

                if (!confirm('Deseja reverter o status de PERDIDO para este livro? O status voltará a ser \'Emprestado\'.')) return;

                fetch('api/reverter_perda.php', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({ emprestimo_id: emprestimoId }) 
                })
                .then(handleResponse)
                .then(result => {
                    if (result.success && result.reverted_loan) {
                        const loan = result.reverted_loan;
                        const cell = document.querySelector(`td[data-livro-id="${livroId}"]`);

                        if (cell) {
                            const conservacao = loan.conservacao_entrega;
                            const newBadge = document.createElement('a');
                            newBadge.href = '#';
                            newBadge.className = `badge ${getConservacaoClass(conservacao)} text-decoration-none btn-devolver`;
                            newBadge.innerHTML = `<i class="bi bi-check-circle-fill"></i> ENTREGUE (${conservacao})`;
                            newBadge.dataset.bsToggle = 'modal';
                            newBadge.dataset.bsTarget = '#devolucaoModal';
                            newBadge.dataset.emprestimoId = loan.emprestimo_id;
                            newBadge.dataset.alunoId = loan.aluno_id;
                            newBadge.dataset.livroId = loan.livro_id;
                            newBadge.dataset.alunoNome = alunoNome;
                            newBadge.dataset.livroTitulo = livroTitulo;
                            newBadge.dataset.conservacaoEntrega = conservacao;

                            cell.innerHTML = '';
                            cell.appendChild(newBadge);
                        }
                        handleSuccess(result, false);
                    } else {
                        handleError(new Error(result.message || 'Não foi possível reverter a perda.'));
                    }
                })
                .catch(handleError);
            });
        });

        document.getElementById('btn-confirmar-entrega').addEventListener('click', function() {
            const data = { 
                aluno_id: alunoId, 
                livro_id: document.getElementById('modal-livro-id').value, 
                conservacao: document.getElementById('conservacao').value, 
                ano_letivo: anoLetivo 
            };
            fetch('api/registrar_emprestimo.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse)
            .then(result => {
                if (result.success && result.novo_emprestimo) {
                    const emp = result.novo_emprestimo;
                    const cell = document.querySelector(`td[data-livro-id="${emp.livro_id}"]`);
                    const alunoNome = document.getElementById('modal-aluno-nome').textContent;
                    const livroTitulo = document.getElementById('modal-livro-titulo').textContent;

                    if (cell) {
                        const newBadge = `
                            <a href="#" class="badge ${getConservacaoClass(emp.conservacao)} text-decoration-none btn-devolver" 
                               data-bs-toggle="modal" 
                               data-bs-target="#devolucaoModal" 
                               data-emprestimo-id="${emp.emprestimo_id}" 
                               data-aluno-id="${emp.aluno_id}" 
                               data-livro-id="${emp.livro_id}" 
                               data-aluno-nome="${alunoNome}" 
                               data-livro-titulo="${livroTitulo}" 
                               data-conservacao-entrega="${emp.conservacao}">
                                <i class="bi bi-check-circle-fill"></i> ENTREGUE (${emp.conservacao})
                            </a>`;
                        cell.innerHTML = newBadge;
                    }
                    entregaModal.hide();
                    handleSuccess(result, false);
                } else {
                    handleError(new Error(result.message || 'Resposta inválida da API.'));
                }
            })
            .catch(handleError);
        });

        document.getElementById('btn-confirmar-devolucao').addEventListener('click', function() {
            const data = { 
                emprestimo_id: document.getElementById('modal-emprestimo-id').value, 
                conservacao: document.getElementById('conservacao_devolucao').value 
            };
            const alunoNome = document.getElementById('modal-devolucao-aluno-nome').textContent;
            const livroTitulo = document.getElementById('modal-devolucao-livro-titulo').textContent;
            const alunoIdDev = document.getElementById('modal-devolucao-aluno-id').value;
            const livroIdDev = document.getElementById('modal-devolucao-livro-id').value;

            fetch('api/registrar_devolucao.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse)
            .then(result => {
                if (result.success && result.devolucao) {
                    const dev = result.devolucao;
                    const cell = document.querySelector(`td[data-livro-id="${livroIdDev}"]`);

                    if (cell) {
                        const newBadge = document.createElement('a');
                        newBadge.className = `badge ${getConservacaoClass(dev.conservacao_devolucao)} text-decoration-none btn-reverter-devolucao`;
                        newBadge.title = 'Reverter Devolução';
                        newBadge.innerHTML = `<i class="bi bi-arrow-counterclockwise"></i> DEVOLVIDO (${dev.conservacao_devolucao})`;
                        newBadge.dataset.emprestimoId = dev.emprestimo_id;
                        newBadge.dataset.alunoId = alunoIdDev;
                        newBadge.dataset.livroId = livroIdDev;
                        newBadge.dataset.alunoNome = alunoNome;
                        newBadge.dataset.livroTitulo = livroTitulo;
                        
                        cell.innerHTML = '';
                        cell.appendChild(newBadge);
                    }
                    
                    devolucaoModal.hide();
                    handleSuccess(result, false);
                } else {
                    devolucaoModal.hide();
                    handleError(new Error(result.message || 'Não foi possível registrar a devolução.'));
                }
            })
            .catch(handleError);
        });

        document.getElementById('btn-cancelar-entrega').addEventListener('click', function() {
            const emprestimoId = document.getElementById('modal-emprestimo-id').value;
            const alunoIdCancel = document.getElementById('modal-devolucao-aluno-id').value;
            const livroIdCancel = document.getElementById('modal-devolucao-livro-id').value;
            const alunoNomeCancel = document.getElementById('modal-devolucao-aluno-nome').textContent;
            const livroTituloCancel = document.getElementById('modal-devolucao-livro-titulo').textContent;

            if (!confirm('Certeza que deseja cancelar este empréstimo?')) {
                return;
            }

            fetch('api/cancelar_emprestimo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ emprestimo_id: emprestimoId }) 
            })
            .then(handleResponse)
            .then(result => {
                if (result.success) {
                    const cell = document.querySelector(`td[data-livro-id="${livroIdCancel}"]`);

                    if (cell) {
                        cell.innerHTML = `<button class="btn btn-primary btn-sm btn-entregar" data-bs-toggle="modal" data-bs-target="#entregaModal" data-livro-id="${livroIdCancel}" data-livro-titulo="${livroTituloCancel}" data-aluno-id="${alunoIdCancel}" data-aluno-nome="${alunoNomeCancel}"><i class="bi bi-plus-circle"></i> Entregar</button>`;
                    }
                    
                    devolucaoModal.hide();
                    handleSuccess(result);
                } else {
                    devolucaoModal.hide();
                    handleError(new Error(result.message || 'Não foi possível cancelar. Resposta da API inválida.'));
                }
            })
            .catch(handleError);
        });

        document.getElementById('btn-confirmar-perda').addEventListener('click', function() {
            const emprestimoId = document.getElementById('modal-emprestimo-id').value;
            const realizarReposicao = document.getElementById('realizar_reposicao').checked;
            const conservacaoReposicao = document.getElementById('conservacao_reposicao').value;
            const alunoIdPerda = document.getElementById('modal-devolucao-aluno-id').value;
            const livroIdPerda = document.getElementById('modal-devolucao-livro-id').value;
            const alunoNomePerda = document.getElementById('modal-devolucao-aluno-nome').textContent;
            const livroTituloPerda = document.getElementById('modal-devolucao-livro-titulo').textContent;
            
            let confirmMessage = 'Tem certeza que deseja marcar este livro como PERDIDO?';
            if (realizarReposicao) {
                confirmMessage += `\n\nUm livro em estado '${conservacaoReposicao}' será retirado da reserva para REPOSIÇÃO.`;
            } else {
                confirmMessage += '\n\nNENHUM livro será retirado da reserva. O empréstimo será encerrado como perdido.';
            }
            if (!confirm(confirmMessage)) return;

            const payload = { 
                emprestimo_id: emprestimoId, 
                realizar_reposicao: realizarReposicao,
                conservacao_reposicao: realizarReposicao ? conservacaoReposicao : null
            };

            fetch('api/marcar_como_perdido.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify(payload) 
            })
            .then(handleResponse)
            .then(result => {
                if (result.success && result.data) {
                    const data = result.data;
                    const cell = document.querySelector(`td[data-livro-id="${livroIdPerda}"]`);

                    if (cell) {
                        if (data.replacement_loan) {
                            const loan = data.replacement_loan;
                            const newBadge = document.createElement('a');
                            newBadge.href = '#';
                            newBadge.className = `badge ${getConservacaoClass(loan.conservacao_entrega)} text-decoration-none btn-devolver`;
                            
                            newBadge.innerHTML = `
                                <i class="bi bi-exclamation-triangle-fill text-warning me-1" title="Este livro foi perdido e reposto"></i>
                                <i class="bi bi-check-circle-fill"></i> ENTREGUE (${loan.conservacao_entrega})
                            `;
                            
                            newBadge.dataset.bsToggle = 'modal';
                            newBadge.dataset.bsTarget = '#devolucaoModal';
                            newBadge.dataset.emprestimoId = loan.emprestimo_id;
                            newBadge.dataset.alunoId = alunoIdPerda;
                            newBadge.dataset.livroId = livroIdPerda;
                            newBadge.dataset.alunoNome = alunoNomePerda;
                            newBadge.dataset.livroTitulo = livroTituloPerda;
                            newBadge.dataset.conservacaoEntrega = loan.conservacao_entrega;
                            
                            cell.innerHTML = '';
                            cell.appendChild(newBadge);

                        } else if (data.lost_loan) {
                            const loan = data.lost_loan;
                            cell.innerHTML = `<a href="#" class="badge bg-danger text-decoration-none btn-reverter-perda" title="Reverter Status de Perda" data-emprestimo-id="${loan.emprestimo_id}" data-aluno-id="${alunoIdPerda}" data-livro-id="${livroIdPerda}" data-aluno-nome="${alunoNomePerda}" data-livro-titulo="${livroTituloPerda}"><i class="bi bi-arrow-counterclockwise"></i> PERDIDO</a>`;
                        }
                    }

                    perdaModal.hide();
                    devolucaoModal.hide();
                    handleSuccess(result, false); // false = no reload

                } else {
                    handleError(new Error(result.message || 'Não foi possível marcar como perdido.'));
                }
            })
            .catch(handleError);
        });

        // --- Histórico Completo ---
        function loadHistorico() {
            console.log('Loading historico...');
            const historicoContent = document.getElementById('historico-aluno-content');
            historicoContent.innerHTML = '<p class="text-muted">Carregando...</p>';
            fetch(`api/get_historico_aluno.php?aluno_id=${alunoId}`)
                .then(handleResponse)
                .then(data => {
                    console.log('History data received:', data);
                    let html = '<table class="table table-sm table-striped table-bordered"><thead><tr><th>Ano</th><th>Livro</th><th>Status</th><th>Conservação</th><th>Data</th></tr></thead><tbody>';
                    if (data.historico && Object.keys(data.historico).length > 0) {
                        for (const ano in data.historico) {
                            data.historico[ano].forEach(item => {
                                const conservacao = item.status === 'Devolvido' ? item.conservacao_devolucao : item.conservacao_entrega;
                                const data_evento = item.status === 'Devolvido' ? item.data_devolucao : item.data_entrega;
                                html += `<tr>
                                    <td>${item.ano_letivo}</td>
                                    <td>${item.livro_titulo}</td>
                                    <td><span class="badge ${getConservacaoClass(conservacao)}">${item.status}</span></td>
                                    <td>${conservacao || 'N/A'}</td>
                                    <td>${data_evento ? new Date(data_evento).toLocaleDateString('pt-BR') : 'N/A'}</td>
                                </tr>`;
                            });
                        }
                    } else {
                        html += '<tr><td colspan="5" class="text-center">Nenhum registro encontrado.</td></tr>';
                    }
                    html += '</tbody></table>';
                    historicoContent.innerHTML = html;
                })
                .catch(err => {
                    console.error('Error loading history:', err);
                    historicoContent.innerHTML = '<p class="text-danger">Erro ao carregar histórico.</p>';
                    handleError(err);
                });
        }
        loadHistorico(); // Call it on page load
    });
    </script>
</body>
</html>