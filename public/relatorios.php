<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

// Busca anos letivos para o dropdown de relatórios anuais
$anos_q = $conn->query("SELECT DISTINCT ano_letivo FROM emprestimos ORDER BY ano_letivo DESC");
$anos_disponiveis = [];
while($ano = $anos_q->fetch_assoc()) {
    $anos_disponiveis[] = $ano['ano_letivo'];
}

// Define os novos rótulos e suas cores para exibição
$display_statuses = [
    'ÓTIMO' => ['bg' => 'bg-primary', 'text' => 'text-white'],
    'BOM' => ['bg' => 'bg-success', 'text' => 'text-white'],
    'REGULAR' => ['bg' => 'bg-warning', 'text' => 'text-dark'],
    'RUIM' => ['bg' => 'bg-danger', 'text' => 'text-white'],
    'PÉSSIMO' => ['bg' => 'bg-black', 'text' => 'text-white'],
    'PERDIDO' => ['bg' => 'bg-orange', 'text' => 'text-white'],
];



$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - Controle Didático</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .table-sm th, .table-sm td {
            padding: 0.4rem;
            vertical-align: middle;
        }
        .accordion-button:not(.collapsed) {
            color: #0c63e4;
            background-color: #e7f1ff;
        }
        .table thead th.bg-success,
        .table thead th.bg-primary,
        .table thead th.bg-warning,
        .table thead th.bg-danger,
        .table thead th.bg-dark,
        .table thead th.bg-black,
        .table thead th.bg-orange { /* Added bg-orange here */
            color: white;
        }
        .table thead th.bg-warning {
            color: black !important;
        }
        .table thead th.bg-orange { /* Made selector more specific */
            background-color: #fd7e14; /* Bootstrap's orange */
        }
    </style>
</head>
<body>
        <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div class="container mt-4">
        <ul class="nav nav-tabs" id="relatoriosTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="inventario-tab" data-bs-toggle="tab" data-bs-target="#inventario" type="button" role="tab" aria-controls="inventario" aria-selected="true">
                    <i class="bi bi-archive-fill"></i> Inventário
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="anuais-tab" data-bs-toggle="tab" data-bs-target="#anuais" type="button" role="tab" aria-controls="anuais" aria-selected="false">
                    <i class="bi bi-file-earmark-text-fill"></i> Relatórios Anuais
                </button>
            </li>
        </ul>

        <div class="tab-content pt-3" id="relatoriosTabContent">
            <!-- Aba de Inventário -->
            <div class="tab-pane fade show active" id="inventario" role="tabpanel" aria-labelledby="inventario-tab">
                <div class="my-3">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-3">
                            <label for="inventarioAnoLetivo" class="form-label">Ano Letivo</label>
                            <select class="form-select" id="inventarioAnoLetivo">
                                <option value="">Selecione...</option>
                                <?php foreach ($anos_disponiveis as $ano): ?>
                                    <option value="<?php echo $ano; ?>" <?php echo ($ano == date('Y')) ? 'selected' : ''; ?>><?php echo $ano; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-auto">
                            <button class="btn btn-primary" onclick="carregarInventario()"><i class="bi bi-search"></i> Carregar Inventário</button>
                        </div>
                    </div>
                </div>
                <div id="inventario-content">
                    <!-- O conteúdo do inventário será carregado aqui via JS -->
                </div>
            </div>

            <!-- Aba de Relatórios Anuais -->
            <div class="tab-pane fade" id="anuais" role="tabpanel" aria-labelledby="anuais-tab">
                <div class="my-3">
                    <h3>Relatórios Anuais para Download</h3>
                    <p>Selecione o ano letivo e o tipo de relatório para gerar o arquivo CSV.</p>

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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Reserva Técnica -->
    <div class="modal fade" id="reservaModal" tabindex="-1" aria-labelledby="reservaModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reservaModalLabel">Gerenciar Reserva Técnica</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="form-reserva">
              <input type="hidden" id="livroIdReserva" name="livro_id">
              <div class="mb-3">
                  <h6 id="livroNomeReserva"></h6>
              </div>
              <div class="mb-3">
                <label for="actionType" class="form-label">Ação</label>
                <select class="form-select" id="actionType" name="action_type">
                    <option value="add">Adicionar Cópias</option>
                    <option value="remove">Remover Cópias</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="quantidadeReserva" class="form-label">Quantidade</label>
                <input type="number" class="form-control" id="quantidadeReserva" name="quantidade" min="1" required>
              </div>
              <div class="mb-3">
                <label for="conservacaoReserva" class="form-label">Estado de Conservação</label>
                <select class="form-select" id="conservacaoReserva" name="conservacao" required>
                    <?php foreach ($display_statuses as $status => $details): ?>
                        <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </form>
          </div>
        </div>
      </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formGerarRelatorio = document.getElementById('form-gerar-relatorio');
            if(formGerarRelatorio) {
                formGerarRelatorio.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const anoLetivo = document.getElementById('anoLetivo').value;
                    const tipoRelatorio = document.getElementById('tipoRelatorio').value;
                    if (!anoLetivo || !tipoRelatorio) {
                        alert('Por favor, selecione o ano letivo e o tipo de relatório.');
                        return;
                    }
                    window.location.href = `api/gerar_relatorio_anual.php?ano=${anoLetivo}&tipo=${tipoRelatorio}`;
                });
            }

            const conservationStatuses = <?php echo json_encode($display_statuses); ?>;
            

            const reservaModalElem = document.getElementById('reservaModal');
            const reservaModal = reservaModalElem ? new bootstrap.Modal(reservaModalElem) : null;

            window.openReservaModal = function(livroId, livroNome) {
                if(reservaModal) {
                    document.getElementById('livroIdReserva').value = livroId;
                    document.getElementById('livroNomeReserva').innerText = `Livro: ${livroNome}`;
                    reservaModal.show();
                }
            }

            const formReserva = document.getElementById('form-reserva');
            if(formReserva) {
                formReserva.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    const form = event.target;
                    const data = {
                        livro_id: form.livro_id.value,
                        action_type: form.action_type.value,
                        quantidade: form.quantidade.value,
                        conservacao: form.conservacao.value,
                        ano_letivo: document.getElementById('inventarioAnoLetivo').value
                    };

                    try {
                        const response = await fetch('api/update_reserva_tecnica.php', {
                            method: 'POST',
                            body: JSON.stringify(data),
                            headers: { 'Content-Type': 'application/json' }
                        });
                        const result = await response.json();
                        if (result.success) {
                            reservaModal.hide();
                            carregarInventario();
                        } else {
                            alert('Erro ao atualizar reserva: ' + result.message);
                        }
                    } catch (error) {
                        alert('Ocorreu um erro de comunicação.');
                        console.error('Erro no form-reserva:', error);
                    }
                });
            }

            window.carregarInventario = async function() {
                const anoLetivo = document.getElementById('inventarioAnoLetivo').value;
                const contentDiv = document.getElementById('inventario-content');
                
                if (!anoLetivo) {
                    contentDiv.innerHTML = '<div class="alert alert-warning">Por favor, selecione um ano letivo.</div>';
                    return;
                }

                if(!contentDiv) return;
                contentDiv.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div></div>';

                try {
                    const [circulacaoRes, devolvidosRes, reservaRes] = await Promise.all([
                        fetch(`api/get_inventario_circulacao.php?ano=${anoLetivo}`),
                        fetch(`api/get_inventario_devolvidos.php?ano=${anoLetivo}`),
                        fetch(`api/get_inventario_reserva_tecnica.php?ano=${anoLetivo}`)
                    ]);

                    if (!circulacaoRes.ok || !devolvidosRes.ok || !reservaRes.ok) {
                        const errorCirc = circulacaoRes.ok ? null : await circulacaoRes.text();
                        const errorDev = devolvidosRes.ok ? null : await devolvidosRes.text();
                        const errorRes = reservaRes.ok ? null : await reservaRes.text();
                        console.error({errorCirc, errorDev, errorRes});
                        throw new Error('Falha ao buscar dados de um ou mais endpoints do inventário.');
                    }

                    const circulacao = await circulacaoRes.json();
                    const devolvidos = await devolvidosRes.json();
                    const reserva = await reservaRes.json();

                    renderInventario(circulacao, devolvidos, reserva);
                } catch (error) {
                    contentDiv.innerHTML = '<div class="alert alert-danger">Erro ao carregar o inventário. Verifique o console para mais detalhes.</div>';
                    console.error('Erro ao carregar inventário:', error);
                }
            }

            function renderInventario(circulacao, devolvidos, reserva) {
                const contentDiv = document.getElementById('inventario-content');
                const allSeries = new Set([...Object.keys(circulacao), ...Object.keys(devolvidos), ...Object.keys(reserva)]);

                if (allSeries.size === 0) {
                    contentDiv.innerHTML = '<div class="alert alert-light text-center">Nenhum dado de inventário encontrado para o ano letivo selecionado.</div>';
                    return;
                }

                let html = '<div class="accordion" id="accordionInventario">';
                
                Array.from(allSeries).sort().forEach((serie, index) => {
                    const serieCirculacao = circulacao[serie] || [];
                    const serieDevolvidos = devolvidos[serie] || [];
                    const serieReserva = reserva[serie] || [];

                    html += `
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-${index}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${index}" aria-expanded="false" aria-controls="collapse-${index}">
                                    <strong>${serie}</strong>
                                </button>
                            </h2>
                            <div id="collapse-${index}" class="accordion-collapse collapse" aria-labelledby="heading-${index}" data-bs-parent="#accordionInventario">
                                <div class="accordion-body">
                                    <h5><i class="bi bi-person-check-fill"></i> Em Circulação (Emprestados)</h5>
                                    ${renderTabelaAgrupada(serieCirculacao, {livro: 'Livro'})}
                                    
                                    <h5 class="mt-4"><i class="bi bi-journal-check"></i> Devolvidos</h5>
                                    ${renderTabelaAgrupada(serieDevolvidos, {livro: 'Livro'})}

                                    <h5 class="mt-4"><i class="bi bi-box-seam-fill"></i> Reserva Técnica</h5>
                                    ${renderTabelaAgrupada(serieReserva, {livro: 'Livro'}, true)}
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                contentDiv.innerHTML = html;
            }

            function renderTabela(data, columns) {
                if (!data || data.length === 0) return '<p class="text-muted">Nenhum registro.</p>';
                let head = Object.values(columns).map(h => `<th>${h}</th>`).join('');
                let body = data.map(row => `<tr>${Object.keys(columns).map(col => `<td>${row[col] || ''}</td>`).join('')}</tr>`).join('');
                return `<table class="table table-sm table-striped table-hover"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
            }

            function renderTabelaAgrupada(data, baseColumns, isReserva = false) {
                if (!data || data.length === 0) return '<p class="text-muted">Nenhum registro.</p>';

                const statuses = Object.keys(conservationStatuses);
                
                const groupedData = data.reduce((acc, row) => {
                    const key = row.livro_id;
                    if (!acc[key]) {
                        acc[key] = { ...row, counts: {} };
                        statuses.forEach(s => acc[key].counts[s] = 0);
                    }
                    const dbStatus = (row.status || 'desconhecido').toUpperCase();
                    const displayStatus = dbStatus;

                    if (acc[key].counts.hasOwnProperty(displayStatus)) {
                        acc[key].counts[displayStatus] += parseInt(row.total || row.quantidade || 0);
                    }
                    return acc;
                }, {});

                let head = Object.values(baseColumns).map(h => `<th>${h}</th>`).join('');
                statuses.forEach(s => {
                    const statusInfo = conservationStatuses[s];
                    head += `<th class="${statusInfo.bg} ${statusInfo.text} text-center">${s}</th>`;
                });
                if (isReserva) head += '<th class="text-center">Ações</th>';

                let body = Object.values(groupedData).map(row => {
                    let baseCells = Object.keys(baseColumns).map(col => `<td>${row[col] || ''}</td>`).join('');
                    let countCells = statuses.map(s => `<td class="text-center">${row.counts[s] || 0}</td>`).join('');
                    let actionCell = isReserva ? `<td class="text-center"><button class="btn btn-sm btn-outline-primary" onclick="openReservaModal(${row.livro_id}, '${row.livro.replace(/'/g, "\\'")}')"><i class="bi bi-pencil-square"></i></button></td>` : '';
                    return `<tr>${baseCells}${countCells}${actionCell}</tr>`;
                }).join('');

                return `<table class="table table-sm table-bordered table-hover"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
            }

            const inventarioTab = document.getElementById('inventario-tab');
            const inventarioAnoLetivoEl = document.getElementById('inventarioAnoLetivo');

            function handleInventarioLoad() {
                if (inventarioAnoLetivoEl.value) {
                    carregarInventario();
                } else {
                    document.getElementById('inventario-content').innerHTML = '<div class="alert alert-info">Selecione um ano letivo para carregar o inventário.</div>';
                }
            }

            if (inventarioTab && inventarioTab.classList.contains('active')) {
                handleInventarioLoad();
            }
            if(inventarioTab) {
                inventarioTab.addEventListener('shown.bs.tab', handleInventarioLoad);
            }
            if(inventarioAnoLetivoEl) {
                inventarioAnoLetivoEl.addEventListener('change', carregarInventario);
            }
        });
    </script>
</body>
</html>
</html>