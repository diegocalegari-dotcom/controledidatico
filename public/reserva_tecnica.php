<?php
require_once '../config/database.php';
require_once 'components/navbar.php';

$conn = connect_db();
$ano_letivo_atual = date('Y');

// Busca inicial dos dados
$livros_sql = "SELECT l.id, l.titulo, s.nome as serie_nome, c.nome as curso_nome
               FROM livros l
               JOIN series s ON l.serie_id = s.id
               JOIN cursos c ON s.curso_id = c.id
               WHERE l.status = 'ATIVO'
               ORDER BY c.nome, s.nome, l.titulo";
$livros_result = $conn->query($livros_sql);

$livros_por_curso_serie = [];
$livros_map = [];
while ($row = $livros_result->fetch_assoc()) {
    $row['reservas'] = ['ÓTIMO' => 0, 'BOM' => 0, 'REGULAR' => 0, 'RUIM' => 0, 'PÉSSIMO' => 0];
    $livros_por_curso_serie[$row['curso_nome']][$row['serie_nome']][] = $row;
    $livros_map[$row['id']] = $row;
}

$reserva_sql = $conn->prepare("SELECT livro_id, conservacao, quantidade FROM reserva_tecnica WHERE ano_letivo = ?");
$reserva_sql->bind_param("s", $ano_letivo_atual);
$reserva_sql->execute();
$reserva_result = $reserva_sql->get_result();

while ($row = $reserva_result->fetch_assoc()) {
    if (isset($livros_map[$row['livro_id']])) {
        // This is tricky to update in the nested array, so we'll handle it with JS
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Reserva Técnica</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-ano-letivo="<?php echo $ano_letivo_atual; ?>">
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1150"></div>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Gerenciar Reserva Técnica</h3>
        </div>

        <div id="livros-container"></div>
    </div>

    <!-- Modal de Gerenciamento de Reserva -->
    <div class="modal fade" id="reservaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gerenciar Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 id="modal-livro-titulo"></h6>
                    <input type="hidden" id="modal-livro-id">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Estado de Conservação</th>
                                <th>Quantidade em Estoque</th>
                            </tr>
                        </thead>
                        <tbody id="modal-reservas-body">
                            <!-- Rows will be inserted by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-salvar-reserva">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Função de Toast (notificação)
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
        const livrosContainer = document.getElementById('livros-container');
        const reservaModal = new bootstrap.Modal(document.getElementById('reservaModal'));
        const modalReservasBody = document.getElementById('modal-reservas-body');
        const anoLetivo = document.body.dataset.anoLetivo;
        let livrosData = [];

        function renderLivros(data) {
            livrosContainer.innerHTML = '';
            const livrosAgrupados = data.reduce((acc, livro) => {
                const curso = livro.curso_nome;
                const serie = livro.serie_nome;
                if (!acc[curso]) acc[curso] = {};
                if (!acc[curso][serie]) acc[curso][serie] = [];
                acc[curso][serie].push(livro);
                return acc;
            }, {});

            for (const curso in livrosAgrupados) {
                const cursoDiv = document.createElement('div');
                cursoDiv.innerHTML = `<h4 class="mt-4">${curso}</h4>`;
                livrosContainer.appendChild(cursoDiv);

                for (const serie in livrosAgrupados[curso]) {
                    const tableId = `table-${curso.replace(/\s/g, '-')}-${serie.replace(/\s/g, '-')}`;
                    const serieHtml = `
                        <h5 class="mt-3 text-muted">${serie}</h5>
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Livro</th>
                                    <th style="width: 200px;">Estoque Total</th>
                                    <th style="width: 220px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="${tableId}">
                                ${livrosAgrupados[curso][serie].map(livro => {
                                    const totalReserva = Object.values(livro.reservas).reduce((sum, current) => sum + current, 0);
                                    return `
                                        <tr data-livro-id="${livro.id}">
                                            <td>${livro.titulo}</td>
                                            <td class="fw-bold align-middle total-reserva-text">${totalReserva}</td>
                                            <td>
                                                <button class="btn btn-primary btn-gerenciar" data-livro-id="${livro.id}">Gerenciar Estoque</button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    `;
                    cursoDiv.insertAdjacentHTML('beforeend', serieHtml);
                }
            }
        }

        function fetchAndRender() {
            fetch('api/get_reserva_tecnica.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        livrosData = result.data;
                        renderLivros(livrosData);
                    } else {
                        showToast('Erro ao carregar dados da reserva.', false);
                    }
                })
                .catch(err => {
                    showToast('Erro de conexão ao carregar dados.', false);
                    console.error(err);
                });
        }

        livrosContainer.addEventListener('click', function(event) {
            if (event.target.classList.contains('btn-gerenciar')) {
                const livroId = event.target.dataset.livroId;
                const livro = livrosData.find(l => l.id == livroId);
                if (livro) {
                    document.getElementById('modal-livro-titulo').textContent = livro.titulo;
                    document.getElementById('modal-livro-id').value = livro.id;
                    
                    modalReservasBody.innerHTML = '';
                    const conservacaoEstados = ['ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO'];
                    conservacaoEstados.forEach(estado => {
                        const quantidade = livro.reservas[estado] || 0;
                        const rowHtml = `
                            <tr>
                                <td class="align-middle">${estado}</td>
                                <td>
                                    <input type="number" class="form-control" data-conservacao="${estado}" value="${quantidade}" min="0">
                                </td>
                            </tr>
                        `;
                        modalReservasBody.insertAdjacentHTML('beforeend', rowHtml);
                    });

                    reservaModal.show();
                }
            }
        });

        document.getElementById('btn-salvar-reserva').addEventListener('click', function() {
            const livroId = document.getElementById('modal-livro-id').value;
            const inputs = modalReservasBody.querySelectorAll('input[type="number"]');
            const promises = [];

            inputs.forEach(input => {
                const conservacao = input.dataset.conservacao;
                const quantidade = parseInt(input.value, 10);
                const livro = livrosData.find(l => l.id == livroId);
                const originalQuantidade = livro.reservas[conservacao] || 0;

                if (quantidade !== originalQuantidade) {
                    const promise = fetch('api/update_reserva_tecnica.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            livro_id: livroId, 
                            conservacao: conservacao, 
                            quantidade: quantidade,
                            ano_letivo: anoLetivo
                        })
                    }).then(res => res.json());
                    promises.push(promise);
                }
            });

            Promise.all(promises)
                .then(results => {
                    const allSuccess = results.every(r => r.success);
                    if (allSuccess) {
                        showToast('Reserva atualizada com sucesso!');
                        reservaModal.hide();
                        fetchAndRender(); // Recarrega os dados para mostrar o total atualizado
                    } else {
                        showToast('Ocorreu um erro ao atualizar uma ou mais entradas da reserva.', false);
                    }
                })
                .catch(err => {
                    showToast('Erro de conexão ao salvar.', false);
                    console.error(err);
                });
        });

        // Carga inicial
        fetchAndRender();
    });
    </script>
</body>
</html>