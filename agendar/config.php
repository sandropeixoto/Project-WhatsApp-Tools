<?php
// agendar/config.php
$is_page_request = true;
require_once __DIR__ . '/api/auth_middleware.php';

if ($user['role'] !== 'owner') {
    echo "<h1>Acesso negado. Apenas o dono da conta pode acessar esta página.</h1>";
    echo "<a href='index.php'>Voltar ao Calendário</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Módulo Agendar</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .navbar-wa {
            background-color: #075E54;
        }

        .navbar-wa .navbar-brand,
        .navbar-wa .nav-link {
            color: white;
        }

        .navbar-wa .nav-link:hover {
            color: #dcf8c6;
        }

        .config-container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-wa sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-calendar-check"></i> WA Agendar</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Calendário</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="config.php"><i class="bi bi-gear"></i> Configurações</a>
                    </li>
                </ul>
                <span class="navbar-text text-light me-3">
                    Logado como:
                    <?= htmlspecialchars($user['phone_number'])?>
                </span>
                <a href="api/auth.php?action=logout" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="config-container">
            <h2 class="mb-4">Configurações Gerais</h2>

            <div id="alertBox" class="alert d-none"></div>

            <div class="row">
                <!-- Conta -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-light fw-bold">Parâmetros de Disparo</div>
                        <div class="card-body">
                            <form id="configForm">
                                <div class="mb-3">
                                    <label class="form-label">Instância de Disparo</label>
                                    <input type="text" class="form-control" id="instanceName"
                                        placeholder="Ex: MinhaInstancia" required>
                                    <div class="form-text">Nome da instância conectada no WA Tools.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Número ou Grupo de Destino</label>
                                    <input type="text" class="form-control" id="targetJid"
                                        placeholder="Ex: 5511999999999 ou id-do-grupo@g.us" required>
                                </div>
                                <button type="submit" class="btn btn-success" id="btnSaveConfig">Salvar
                                    Configurações</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Usuários -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                            Equipe (Acesso Compartilhado)
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#inviteModal"><i class="bi bi-person-plus"></i> Convidar</button>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush" id="usersList">
                                <li class="list-group-item text-center">Carregando...</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Convite -->
    <div class="modal fade" id="inviteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Convidar Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="inviteForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Número do WhatsApp (com DDI)</label>
                            <input type="text" class="form-control" id="invitePhone" placeholder="5511999999999"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary" id="btnInvite">Enviar Convite</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const configForm = document.getElementById('configForm');
            const inviteForm = document.getElementById('inviteForm');
            const alertBox = document.getElementById('alertBox');

            function showAlert(msg, type = 'success') {
                alertBox.className = `alert alert-${type} mb-4`;
                alertBox.innerText = msg;
            }

            async function loadConfig() {
                try {
                    const res = await fetch('api/config.php?action=get');
                    const data = await res.json();

                    if (data.account) {
                        document.getElementById('instanceName').value = data.account.instance_name || '';
                        document.getElementById('targetJid').value = data.account.target_jid || '';
                    }

                    if (data.users) {
                        const ul = document.getElementById('usersList');
                        ul.innerHTML = '';
                        data.users.forEach(u => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center';

                            let badge = u.role === 'owner' ? '<span class="badge bg-primary rounded-pill">Dono</span>' : '<span class="badge bg-secondary rounded-pill">Convidado</span>';
                            let delBtn = u.role === 'owner' ? '' : `<button class="btn btn-sm btn-outline-danger" onclick="removeUser(${u.id})"><i class="bi bi-trash"></i></button>`;

                            li.innerHTML = `
                            <div>
                                ${u.phone_number} ${badge}
                            </div>
                            ${delBtn}
                        `;
                            ul.appendChild(li);
                        });
                    }
                } catch (e) {
                    showAlert('Erro ao carregar dados.', 'danger');
                }
            }

            configForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('btnSaveConfig');
                btn.disabled = true;

                const inst = document.getElementById('instanceName').value;
                const jid = document.getElementById('targetJid').value;

                try {
                    const res = await fetch('api/config.php?action=update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ instance_name: inst, target_jid: jid })
                    });
                    if (res.ok) showAlert('Configurações salvas!');
                    else showAlert('Erro ao salvar.', 'danger');
                } catch (e) {
                    showAlert('Erro de conexão.', 'danger');
                }
                btn.disabled = false;
            });

            inviteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('btnInvite');
                btn.disabled = true;
                const phone = document.getElementById('invitePhone').value;

                try {
                    const res = await fetch('api/config.php?action=invite', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ phone: phone })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        showAlert('Usuário convidado. Ele já pode fazer login no sistema.');
                        bootstrap.Modal.getInstance(document.getElementById('inviteModal')).hide();
                        document.getElementById('invitePhone').value = '';
                        loadConfig();
                    } else {
                        alert(data.error);
                    }
                } catch (e) {
                    alert('Erro de conexão.');
                }
                btn.disabled = false;
            });

            window.removeUser = async function (id) {
                if (!confirm("Remover o acesso deste usuário?")) return;
                try {
                    const res = await fetch('api/config.php?action=remove_user', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    if (res.ok) {
                        showAlert('Usuário removido.');
                        loadConfig();
                    } else {
                        const data = await res.json();
                        alert(data.error);
                    }
                } catch (e) {
                    alert('Erro de conexão.');
                }
            }

            loadConfig();
        });
    </script>
</body>

</html>