<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
if ($turma_id <= 0) {
    header("Location: index.php");
    exit;
}

// Busca anos letivos para o seletor
$anos_letivos_q = $conn->query("SELECT ano, sessao_ativa FROM anos_letivos ORDER BY ano DESC");
$anos_disponiveis = [];
$sessoes_por_ano = [];
while($ano_row = $anos_letivos_q->fetch_assoc()) {
    $anos_disponiveis[] = $ano_row['ano'];
    $sessoes_por_ano[$ano_row['ano']] = $ano_row['sessao_ativa'];
}

// Busca o ano letivo atualmente ativo na configuração
$ano_ativo_q = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo' LIMIT 1");
$ano_letivo_ativo = $ano_ativo_q->fetch_assoc()['valor'] ?? date('Y');

// Define o ano letivo a ser exibido: usa o do GET, ou o ativo, ou o mais recente
$ano_letivo_selecionado = isset($_GET['ano_letivo']) ? (int)$_GET['ano_letivo'] : $ano_letivo_ativo;

// Busca a sessão ativa para o ano selecionado
$sessao_ativa = $sessoes_por_ano[$ano_letivo_selecionado] ?? 'ENTREGA';

if (!in_array($ano_letivo_selecionado, $anos_disponiveis) && !empty($anos_disponiveis)) {
    array_unshift($anos_disponiveis, $ano_letivo_selecionado);
    sort($anos_disponiveis);
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
$emprestimos_q = $conn->prepare("SELECT id as emprestimo_id, estudante_id, livro_id, status, conservacao_entrega, conservacao_devolucao FROM emprestimos WHERE ano_letivo = ?");
$emprestimos_q->bind_param("s", $ano_letivo_selecionado);
$emprestimos_q->execute();
$emprestimos_r = $emprestimos_q->get_result();
$emprestimos = [];
while($e = $emprestimos_r->fetch_assoc()) {
    $emprestimos[$e['estudante_id']][$e['livro_id']] = [
        'emprestimo_id' => $e['emprestimo_id'],
        'status' => $e['status'],
        'conservacao' => $e['conservacao_entrega'],
        'conservacao_devolucao' => $e['conservacao_devolucao']
    ];
}

// Busca todas as outras turmas para o remanejamento
$outras_turmas_q = $conn->prepare("SELECT t.id, CONCAT(s.nome, ' - ', t.nome) as nome_completo FROM turmas t JOIN series s ON t.serie_id = s.id WHERE t.id != ? ORDER BY nome_completo");
$outras_turmas_q->bind_param("i", $turma_id);
$outras_turmas_q->execute();
$outras_turmas = $outras_turmas_q->get_result();

$conn->close();

// Função auxiliar para mapear estado de conservação para classe de cor
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
    <title>Controle da Turma</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-responsive { max-height: 75vh; }
        .table td, .table th { text-align: center; vertical-align: middle; height: 55px; }
        .table th { width: 200px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .btn-reverter-devolucao { cursor: pointer; text-decoration: underline; }
        .row-selected {
            background-color: #cfe2ff !important; /* Bootstrap's primary-light color */
        }
    </style>
</head>
<body data-ano-atual="<?php echo $ano_letivo_selecionado; ?>" data-sessao-ativa="<?php echo $sessao_ativa; ?>">
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <h3 class="me-3">Controle da Turma: <?php echo htmlspecialchars($turma['serie_nome'] . ' - ' . $turma['turma_nome']); ?></h3>
                <select class="form-select" id="anoLetivoSelector" style="width: auto;">
                    <?php foreach ($anos_disponiveis as $ano): ?>
                        <option value="<?php echo $ano; ?>" <?php echo ($ano == $ano_letivo_selecionado) ? 'selected' : ''; ?>><?php echo $ano; ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="badge fs-6 ms-3 <?php echo $sessao_ativa == 'DEVOLUCAO' ? 'bg-warning text-dark' : 'bg-info'; ?>">
                    Sessão Ativa: <?php echo ucfirst(strtolower($sessao_ativa)); ?>
                </span>
            </div>
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
                        <th style="width: 180px;">Ações em Massa <small class="d-block fw-normal">(R na linha p/ reverter)</small></th>
                        <?php foreach ($livros_array as $livro): ?>
                            <th title="<?php echo htmlspecialchars($livro['titulo']); ?>"><?php echo htmlspecialchars($livro['titulo']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alunos->num_rows > 0): while($aluno = $alunos->fetch_assoc()): ?>
                        <tr data-aluno-id="<?php echo $aluno['id']; ?>">
                            <td>
                                <button class="btn btn-sm btn-outline-secondary btn-gerenciar-aluno" data-aluno-id="<?php echo $aluno['id']; ?>" title="Gerenciar Aluno">
                                    <i class="bi bi-gear"></i>
                                </button>
                            </td>
                            <td class="text-start ps-2"><?php echo htmlspecialchars($aluno['nome']); ?></td>
                            <td>
                                <?php if ($sessao_ativa == 'ENTREGA'): ?>
                                    <button class="btn btn-sm btn-success btn-entregar-todos" data-aluno-id="<?php echo $aluno['id']; ?>" title="Entregar todos os livros pendentes para este aluno com qualidade BOM">
                                        <i class="bi bi-check-all"></i> Entregar Todos
                                    </button>
                                <?php else: // DEVOLUCAO ?>
                                    <button class="btn btn-sm btn-info btn-devolver-todos" data-aluno-id="<?php echo $aluno['id']; ?>" title="Devolver todos os livros pendentes para este aluno">
                                        <i class="bi bi-arrow-return-left"></i> Devolver Todos
                                    </button>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($livros_array as $livro): ?>
                                <td data-livro-id="<?php echo $livro['id']; ?>">
                                    <?php 
                                    $emprestimo = $emprestimos[$aluno['id']][$livro['id']] ?? null;
                                    if ($emprestimo) {
                                        switch ($emprestimo['status']) {
                                            case 'Emprestado':
                                                $conservacao = htmlspecialchars($emprestimo['conservacao']);
                                                $classe_cor = get_conservacao_class($conservacao);
                                                echo '<a href="#" class="badge ' . $classe_cor . ' text-decoration-none btn-devolver" data-bs-toggle="modal" data-bs-target="#devolucaoModal" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-id="' . $aluno['id'] . '" data-livro-id="' . $livro['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-conservacao-entrega="' . $conservacao . '"
                                                       ><i class="bi bi-check-circle-fill"></i> ENTREGUE (' . $conservacao . ')</a>';
                                                break;
                                            case 'Devolvido':
                                                $conservacao_devolucao = htmlspecialchars($emprestimo['conservacao_devolucao']);
                                                $classe_cor = get_conservacao_class($conservacao_devolucao);
                                                echo '<a class="badge ' . $classe_cor . ' text-decoration-none btn-reverter-devolucao" title="Reverter Devolução" data-emprestimo-id="' . $emprestimo['emprestimo_id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '"><i class="bi bi-arrow-counterclockwise"></i> DEVOLVIDO (' . $conservacao_devolucao . ')</a>';
                                                break;
                                            case 'Perdido':
                                                echo '<span class="badge bg-danger text-white"><i class="bi bi-exclamation-triangle-fill"></i> Perdido</span>';
                                                break;
                                        }
                                    } else {
                                        $disabled = $sessao_ativa == 'DEVOLUCAO' ? 'disabled' : '';
                                        echo '<button class="btn btn-primary btn-sm btn-entregar" data-bs-toggle="modal" data-bs-target="#entregaModal" data-livro-id="' . $livro['id'] . '" data-livro-titulo="' . htmlspecialchars($livro['titulo']) . '" data-aluno-id="' . $aluno['id'] . '" data-aluno-nome="' . htmlspecialchars($aluno['nome']) . '" '.$disabled.'><i class="bi bi-plus-circle"></i> Entregar</button>';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?php echo count($livros_array) + 3; ?>" class="text-center">Nenhum aluno encontrado nesta turma.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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
                        <button type="button" class="btn btn-warning ms-2" id="btn-marcar-perdido">(M) Marcar como Perdido</button>
                    </div>
                    <div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button><button type="button" class="btn btn-primary" id="btn-confirmar-devolucao">(D) Confirmar Devolução</button></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Adicionar Aluno -->
    <div class="modal fade" id="adicionarAlunoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Adicionar Novo Aluno à Turma</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label for="novo_aluno_nome" class="form-label">Nome Completo</label><input type="text" class="form-control" id="novo_aluno_nome" required></div>
                    <div class="mb-3"><label for="novo_aluno_cgm" class="form-label">CGM</label><input type="text" class="form-control" id="novo_aluno_cgm" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btn-salvar-novo-aluno">Salvar Aluno</button></div>
            </div>
        </div>
    </div>

    <!-- Modal de Gerenciamento de Aluno -->
    <div class="modal fade" id="gerenciarAlunoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Gerenciar Aluno</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Aluno:</strong> <span id="modal-gerenciar-aluno-nome"></span></p>
                    <input type="hidden" id="modal-gerenciar-aluno-id">
                    <ul class="nav nav-tabs" id="gerenciarAlunoTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="gerenciar-tab" data-bs-toggle="tab" data-bs-target="#gerenciar-pane" type="button">Gerenciar</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico-pane" type="button">Histórico</button></li>
                    </ul>
                    <div class="tab-content" id="gerenciarAlunoTabsContent">
                        <div class="tab-pane fade show active" id="gerenciar-pane" role="tabpanel">
                            <div class="mt-3">
                                <label for="remanejar_turma_id" class="form-label">Remanejar para outra Turma</label>
                                <select class="form-select" id="remanejar_turma_id"><option value="">Selecione...</option><?php mysqli_data_seek($outras_turmas, 0); while($t = $outras_turmas->fetch_assoc()): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome_completo']); ?></option><?php endwhile; ?></select>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="historico-pane" role="tabpanel"><div id="historico-aluno-content" class="mt-3"><p class="text-muted">Carregando...</p></div></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-danger" id="btn-remover-aluno">Remover da Turma</button>
                    <div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="btn-salvar-remanejamento">Salvar</button></div>
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
        document.addEventListener('keydown', function(event) {
            // Ignore if a modal is open or if typing in an input
            if (document.querySelector('.modal.show') || event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT') {
                return;
            }

            if (event.key.toLowerCase() === 'r') {
                const selectedRow = document.querySelector('.row-selected');
                if (selectedRow) {
                    const revertButton = selectedRow.querySelector('.btn-reverter-devolucao');
                    if (revertButton) {
                        event.preventDefault();
                        revertButton.click();
                    } else {
                        showToast('Nenhuma devolução para reverter neste aluno.', false);
                    }
                }
            }
        });
        const entregaModalEl = document.getElementById('entregaModal');
        const devolucaoModalEl = document.getElementById('devolucaoModal');
        const entregaModal = new bootstrap.Modal(entregaModalEl);
        const devolucaoModal = new bootstrap.Modal(devolucaoModalEl);
        const gerenciarAlunoModal = new bootstrap.Modal(document.getElementById('gerenciarAlunoModal'));
        const adicionarAlunoModal = new bootstrap.Modal(document.getElementById('adicionarAlunoModal'));

        const urlParams = new URLSearchParams(window.location.search);
        const turmaId = urlParams.get('turma_id');
        const anoLetivo = document.body.dataset.anoAtual;

        // Inicializa a cor dos seletores quando o modal é aberto
        entregaModalEl.addEventListener('shown.bs.modal', function () {
            updateSelectColor(document.getElementById('conservacao'));
        });
        devolucaoModalEl.addEventListener('shown.bs.modal', function () {
            updateSelectColor(document.getElementById('conservacao_devolucao'));
        });

        devolucaoModalEl.addEventListener('keydown', function(event) {
            if (devolucaoModalEl.style.display === 'none' || devolucaoModalEl.style.visibility === 'hidden') {
                return;
            }
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT' || event.target.tagName === 'TEXTAREA') {
                return;
            }

            switch (event.key.toLowerCase()) {
                case 'c':
                    event.preventDefault();
                    document.getElementById('btn-cancelar-entrega').click();
                    break;
                case 'm':
                    event.preventDefault();
                    document.getElementById('btn-marcar-perdido').click();
                    break;
                case 'd':
                    event.preventDefault();
                    document.getElementById('btn-confirmar-devolucao').click();
                    break;
            }
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
            } else {
                showToast(result.message, false);
            }
        }

        function handleError(error) {
            console.error('Erro na operação:', error);
            showToast(error.message, false);
        }

        // --- Event Listeners ---
        document.getElementById('anoLetivoSelector').addEventListener('change', function() {
            window.location.href = `controle_turma.php?turma_id=${turmaId}&ano_letivo=${this.value}`;
        });

        document.querySelector('tbody').addEventListener('click', function(event) {
            const tr = event.target.closest('tr');
            if (tr) {
                // Remove selection from other rows
                tr.parentElement.querySelectorAll('.row-selected').forEach(selectedRow => {
                    selectedRow.classList.remove('row-selected');
                });
                // Add selection to clicked row
                tr.classList.add('row-selected');
            }

            const target = event.target.closest('button, a');
            if (!target) return;

            const alunoRow = target.closest('tr');
            const alunoNome = alunoRow.querySelector('td:nth-child(2)').textContent;

            if (target.classList.contains('btn-entregar')) {
                document.getElementById('modal-aluno-nome').textContent = target.dataset.alunoNome;
                document.getElementById('modal-livro-titulo').textContent = target.dataset.livroTitulo;
                document.getElementById('modal-aluno-id').value = target.dataset.alunoId;
                document.getElementById('modal-livro-id').value = target.dataset.livroId;
                entregaModal.show();
            } 
            else if (target.classList.contains('btn-devolver')) {
                const conservacaoEntrega = target.dataset.conservacaoEntrega;
                const conservacaoSpan = document.getElementById('modal-devolucao-conservacao-entrega');
                
                document.getElementById('modal-devolucao-aluno-nome').textContent = target.dataset.alunoNome;
                document.getElementById('modal-devolucao-livro-titulo').textContent = target.dataset.livroTitulo;
                document.getElementById('modal-emprestimo-id').value = target.dataset.emprestimoId;
                document.getElementById('modal-devolucao-aluno-id').value = target.dataset.alunoId;
                document.getElementById('modal-devolucao-livro-id').value = target.dataset.livroId;
                
                conservacaoSpan.textContent = conservacaoEntrega;
                conservacaoSpan.className = 'badge '; // Limpa classes antigas
                conservacaoSpan.classList.add(getConservacaoClass(conservacaoEntrega));
                
                devolucaoModal.show();
            } 
            else if (target.classList.contains('btn-reverter-devolucao')) {
                const emprestimoId = target.dataset.emprestimoId;
                const alunoNome = target.dataset.alunoNome;
                const livroTitulo = target.dataset.livroTitulo;

                if (!confirm(`Deseja reverter a devolução do livro "${livroTitulo}" para o aluno ${alunoNome}?`)) return;
                
                fetch('api/reverter_devolucao.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ emprestimo_id: emprestimoId }) })
                .then(handleResponse)
                .then(result => {
                    if (result.success && result.reverted_loan) {
                        const loan = result.reverted_loan;
                        const alunoRow = document.querySelector(`tr[data-aluno-id="${loan.aluno_id}"]`);
                        const cell = alunoRow ? alunoRow.querySelector(`td[data-livro-id="${loan.livro_id}"]`) : null;

                        if (cell) {
                            const conservacao = loan.conservacao_entrega;
                            const classe_cor = getConservacaoClass(conservacao);
                            const newBadge = `
                                <a href="#" class="badge ${classe_cor} text-decoration-none btn-devolver" 
                                   data-bs-toggle="modal" 
                                   data-bs-target="#devolucaoModal" 
                                   data-emprestimo-id="${loan.emprestimo_id}" 
                                   data-aluno-id="${loan.aluno_id}" 
                                   data-livro-id="${loan.livro_id}" 
                                   data-aluno-nome="${alunoNome}" 
                                   data-livro-titulo="${livroTitulo}" 
                                   data-conservacao-entrega="${conservacao}">
                                    <i class="bi bi-check-circle-fill"></i> ENTREGUE (${conservacao})
                                </a>`;
                            cell.innerHTML = newBadge;
                        }
                        handleSuccess(result, false);
                    } else {
                        handleError(new Error(result.message || 'Não foi possível reverter a devolução.'));
                    }
                })
                .catch(handleError);
            } 
            else if (target.classList.contains('btn-gerenciar-aluno')) {
                const alunoId = target.dataset.alunoId;
                document.getElementById('modal-gerenciar-aluno-nome').textContent = alunoNome;
                document.getElementById('modal-gerenciar-aluno-id').value = alunoId;
                gerenciarAlunoModal.show();
            } 
            else if (target.classList.contains('btn-devolver-todos')) {
                const alunoId = target.dataset.alunoId;
                if (!confirm(`Devolver TODOS os livros pendentes para ${alunoNome}?`)) return;
                
                fetch('api/registrar_devolucao_massa.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ aluno_id: alunoId, ano_letivo: anoLetivo })
                })
                .then(handleResponse)
                .then(result => {
                    if (result.success && result.devolucoes) {
                        result.devolucoes.forEach(dev => {
                            const badge = alunoRow.querySelector(`.btn-devolver[data-emprestimo-id="${dev.emprestimo_id}"]`);
                            if (badge) {
                                const cell = badge.parentElement;
                                const newBadge = `
                                    <a class="badge ${getConservacaoClass(dev.conservacao_devolucao)} text-decoration-none btn-reverter-devolucao" 
                                       title="Reverter Devolução" 
                                       data-emprestimo-id="${dev.emprestimo_id}" 
                                       data-aluno-nome="${alunoNome}" 
                                       data-livro-titulo="${badge.dataset.livroTitulo}">
                                        <i class="bi bi-arrow-counterclockwise"></i> DEVOLVIDO (${dev.conservacao_devolucao})
                                    </a>`;
                                cell.innerHTML = newBadge;
                            }
                        });
                        handleSuccess(result, false);
                    } else {
                        handleError(new Error(result.message || 'Resposta inválida da API.'));
                    }
                })
                .catch(handleError);
            } 
            else if (target.classList.contains('btn-entregar-todos')) {
                const alunoId = target.dataset.alunoId;
                const livrosIds = Array.from(alunoRow.querySelectorAll('.btn-entregar')).map(btn => btn.dataset.livroId);

                if (livrosIds.length === 0) {
                    showToast('Este aluno já possui todos os livros ou não há livros a entregar.', false);
                    return;
                }
                if (!confirm(`Entregar TODOS os livros pendentes para ${alunoNome} como \'BOM\'?`)) return;

                fetch('api/registrar_entrega_massa.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ aluno_id: alunoId, livros_ids: livrosIds, ano_letivo: anoLetivo })
                })
                .then(handleResponse)
                .then(result => {
                    if (result.success && result.novos_emprestimos) {
                        result.novos_emprestimos.forEach(emp => {
                            const btn = alunoRow.querySelector(`.btn-entregar[data-livro-id="${emp.livro_id}"]`);
                            if (btn) {
                                const cell = btn.parentElement;
                                const newBadge = `
                                    <a href="#" class="badge ${getConservacaoClass(emp.conservacao)} text-decoration-none btn-devolver" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#devolucaoModal" 
                                       data-emprestimo-id="${emp.emprestimo_id}" 
                                       data-aluno-nome="${alunoNome}" 
                                       data-livro-titulo="${btn.dataset.livroTitulo}" 
                                       data-conservacao-entrega="${emp.conservacao}">
                                        <i class="bi bi-check-circle-fill"></i> ENTREGUE (${emp.conservacao})
                                    </a>`;
                                cell.innerHTML = newBadge;
                            }
                        });
                        handleSuccess(result, false);
                    } else {
                        handleError(new Error(result.message || 'Resposta inválida da API.'));
                    }
                })
                .catch(handleError);
            }
        });

        // Listeners dos botões nos modais
        document.getElementById('btn-confirmar-entrega').addEventListener('click', function() {
            const data = { 
                aluno_id: document.getElementById('modal-aluno-id').value, 
                livro_id: document.getElementById('modal-livro-id').value, 
                conservacao: document.getElementById('conservacao').value, 
                ano_letivo: anoLetivo 
            };
            fetch('api/registrar_emprestimo.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse)
            .then(result => {
                if (result.success && result.novo_emprestimo) {
                    const emp = result.novo_emprestimo;
                    const alunoRow = document.querySelector(`tr[data-aluno-id="${emp.aluno_id}"]`);
                    const cell = alunoRow.querySelector(`td[data-livro-id="${emp.livro_id}"]`);
                    const alunoNome = alunoRow.querySelector('td:nth-child(2)').textContent;

                    if (cell) {
                        const newBadge = `
                            <a href="#" class="badge ${getConservacaoClass(emp.conservacao)} text-decoration-none btn-devolver" 
                               data-bs-toggle="modal" 
                               data-bs-target="#devolucaoModal" 
                               data-emprestimo-id="${emp.emprestimo_id}" 
                               data-aluno-nome="${alunoNome}" 
                               data-livro-titulo="${cell.querySelector('button').dataset.livroTitulo}" 
                               data-conservacao-entrega="${emp.conservacao}">
                                <i class="bi bi-check-circle-fill"></i> ENTREGUE (${emp.conservacao})
                            </a>`;
                        cell.innerHTML = newBadge;
                    }
                    entregaModal.hide();
                    handleSuccess(result, false); // Não recarrega
                } else {
                    handleError(new Error(result.message || 'Resposta inválida da API.'));
                }
            })
            .catch(handleError);
        });

        document.getElementById('btn-confirmar-devolucao').addEventListener('click', function() {
            const data = { emprestimo_id: document.getElementById('modal-emprestimo-id').value, conservacao: document.getElementById('conservacao_devolucao').value };
            fetch('api/registrar_devolucao.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse).then(result => { 
                devolucaoModal.hide();
                handleSuccess(result, true); // Força reload para devolução individual por enquanto
            }).catch(handleError);
        });

        document.getElementById('btn-cancelar-entrega').addEventListener('click', function() {
            const emprestimoId = document.getElementById('modal-emprestimo-id').value;
            const alunoId = document.getElementById('modal-devolucao-aluno-id').value;
            const livroId = document.getElementById('modal-devolucao-livro-id').value;

            if (!confirm('Deseja CANCELAR esta entrega? A anotação será removida permanentemente.')) return;

            fetch('api/cancelar_emprestimo.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ emprestimo_id: emprestimoId }) })
            .then(handleResponse)
            .then(result => {
                if (result.success) {
                    devolucaoModal.hide();
                    
                    const alunoRow = document.querySelector(`tr[data-aluno-id="${alunoId}"]`);
                    const cell = alunoRow ? alunoRow.querySelector(`td[data-livro-id="${livroId}"]`) : null;

                    if (cell) {
                        const livroTitulo = document.getElementById('modal-devolucao-livro-titulo').textContent;
                        const alunoNome = document.getElementById('modal-devolucao-aluno-nome').textContent;
                        const sessaoAtiva = document.body.dataset.sessaoAtiva;
                        const disabled = sessaoAtiva === 'DEVOLUCAO' ? 'disabled' : '';

                        cell.innerHTML = `<button class="btn btn-primary btn-sm btn-entregar" data-bs-toggle="modal" data-bs-target="#entregaModal" data-livro-id="${livroId}" data-livro-titulo="${livroTitulo}" data-aluno-id="${alunoId}" data-aluno-nome="${alunoNome}" ${disabled}><i class="bi bi-plus-circle"></i> Entregar</button>`;
                    }
                    
                    handleSuccess(result, false); // Set reload to false
                } else {
                    handleError(new Error(result.message));
                }
            })
            .catch(handleError);
        });

        document.getElementById('btn-marcar-perdido').addEventListener('click', function() {
            const emprestimoId = document.getElementById('modal-emprestimo-id').value;
            if (!confirm('Tem certeza que deseja marcar este livro como PERDIDO?')) return;
            fetch('api/marcar_como_perdido.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ emprestimo_id: emprestimoId }) })
            .then(handleResponse).then(result => handleSuccess(result, true)).catch(handleError);
        });

        document.getElementById('btn-salvar-novo-aluno').addEventListener('click', function() {
            const nome = document.getElementById('novo_aluno_nome').value;
            const cgm = document.getElementById('novo_aluno_cgm').value;
            if (!nome || !cgm) { alert('Preencha todos os campos.'); return; }
            const data = { nome: nome, cgm: cgm, turma_id: turmaId };
            fetch('api/adicionar_aluno.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse).then(result => handleSuccess(result, true)).catch(handleError);
        });

        document.getElementById('btn-salvar-remanejamento').addEventListener('click', function() {
            const alunoId = document.getElementById('modal-gerenciar-aluno-id').value;
            const novaTurmaId = document.getElementById('remanejar_turma_id').value;
            if (!novaTurmaId) { alert('Selecione uma turma.'); return; }
            if (!confirm('Deseja remanejar este aluno?')) return;
            const data = { aluno_id: alunoId, nova_turma_id: novaTurmaId };
            fetch('api/remanejar_aluno.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse).then(result => handleSuccess(result, true)).catch(handleError);
        });

        document.getElementById('btn-remover-aluno').addEventListener('click', function() {
            const alunoId = document.getElementById('modal-gerenciar-aluno-id').value;
            if (!confirm('ATENÇÃO: O aluno será removido desta turma. Deseja continuar?')) return;
            const data = { aluno_id: alunoId };
            fetch('api/remover_aluno_turma.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(handleResponse).then(result => handleSuccess(result, true)).catch(handleError);
        });

        document.getElementById('historico-tab').addEventListener('shown.bs.tab', function (event) {
            const alunoId = document.getElementById('modal-gerenciar-aluno-id').value;
            const historicoContent = document.getElementById('historico-aluno-content');
            historicoContent.innerHTML = '<p class="text-muted">Carregando...</p>';
            fetch(`api/get_historico_aluno.php?aluno_id=${alunoId}`)
                .then(handleResponse)
                .then(data => {
                    let html = '<ul class="list-group list-group-flush">';
                    if (data.historico && data.historico.length > 0) {
                        data.historico.forEach(item => {
                            html += `<li class="list-group-item"><strong>${item.livro_titulo}</strong> (${item.ano_letivo}) - Status: ${item.status}</li>`;
                        });
                    } else {
                        html += '<li class="list-group-item">Nenhum registro encontrado.</li>';
                    }
                    html += '</ul>';
                    historicoContent.innerHTML = html;
                })
                .catch(err => {
                    historicoContent.innerHTML = '<p class="text-danger">Erro ao carregar histórico.</p>';
                    handleError(err);
                });
        });
    });
    </script>
</body>
</html>