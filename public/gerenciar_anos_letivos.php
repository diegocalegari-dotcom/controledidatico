<?php
require_once '../config/database.php';
require_once 'components/navbar.php';
$conn = connect_db();

$mensagem = '';

// Lida com a requisição para definir um novo ano ativo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_active_year'])) {
    $novo_ano_ativo = (int)$_POST['set_active_year'];
    $stmt = $conn->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'ano_letivo_ativo'");
    $stmt->bind_param("s", $novo_ano_ativo);
    if ($stmt->execute()) {
        $mensagem = '<div class="alert alert-success">Ano letivo ativo alterado para '.$novo_ano_ativo.' com sucesso!</div>';
    } else {
        $mensagem = '<div class="alert alert-danger">Erro ao alterar o ano letivo ativo.</div>';
    }
    $stmt->close();
}

// Busca o ano letivo atualmente ativo
$ano_ativo_q = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'ano_letivo_ativo' LIMIT 1");
$ano_letivo_ativo = $ano_ativo_q->fetch_assoc()['valor'] ?? date('Y');

// Busca todos os anos letivos existentes, incluindo a sessão ativa
$anos_letivos_q = $conn->query("SELECT id, ano, sessao_ativa FROM anos_letivos ORDER BY ano DESC");
$anos_letivos = $anos_letivos_q->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Anos Letivos - Controle Didático</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php render_navbar(basename($_SERVER['PHP_SELF'])); ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1>Gerenciar Anos Letivos</h1>
            <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>
        </div>
        <?php echo $mensagem; ?>

        <div class="card mb-4">
            <div class="card-header">Adicionar Novo Ano Letivo</div>
            <div class="card-body">
                <form id="form-adicionar-ano" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="novoAno" class="form-label">Ano</label>
                        <input type="number" class="form-control" id="novoAno" placeholder="Ex: <?php echo date('Y') + 1; ?>" required>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Adicionar Ano</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Anos Letivos Existentes</div>
            <div class="card-body">
                <?php if (!empty($anos_letivos)):
                ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Ano</th>
                                <th>Sessão de Trabalho</th>
                                <th style="width: 200px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($anos_letivos as $ano_item): ?>
                            <tr>
                                <td class="align-middle"><h4><?php echo htmlspecialchars($ano_item['ano']); ?></h4></td>
                                <td class="align-middle">
                                    <select class="form-select sessao-select" data-ano-id="<?php echo $ano_item['id']; ?>" style="width: auto;">
                                        <option value="ENTREGA" <?php echo ($ano_item['sessao_ativa'] == 'ENTREGA') ? 'selected' : ''; ?>>
                                            Entrega
                                        </option>
                                        <option value="DEVOLUCAO" <?php echo ($ano_item['sessao_ativa'] == 'DEVOLUCAO') ? 'selected' : ''; ?>>
                                            Devolução
                                        </option>
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <?php if ($ano_item['ano'] == $ano_letivo_ativo): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Ativo</span>
                                    <?php else: ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="set_active_year" value="<?php echo $ano_item['ano']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Tornar Ativo</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Nenhum ano letivo cadastrado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, success) {
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

        document.getElementById('form-adicionar-ano').addEventListener('submit', function(event) {
            event.preventDefault();
            const novoAno = document.getElementById('novoAno').value;

            if (!novoAno) {
                alert('Por favor, insira um ano.');
                return;
            }

            fetch('api/criar_ano_letivo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ ano: novoAno })
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, true);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Erro: ' + result.message);
                }
            })
            .catch(err => alert('Erro de comunicação: ' + err));
        });

        document.querySelectorAll('.sessao-select').forEach(select => {
            select.addEventListener('change', function() {
                const anoId = this.dataset.anoId;
                const novaSessao = this.value;

                fetch('api/atualizar_sessao_letiva.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ ano_id: anoId, sessao: novaSessao })
                })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        showToast(result.message, true);
                    } else {
                        showToast('Erro: ' + result.message, false);
                    }
                })
                .catch(err => showToast('Erro de comunicação: ' + err, false));
            });
        });
    </script>
</body>
</html>
