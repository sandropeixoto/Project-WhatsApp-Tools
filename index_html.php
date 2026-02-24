<?php
// HTML PORTION ONLY
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel —
        <?= htmlspecialchars($activeInstanceName)?>
    </title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wa-primary: #00a884;
            --wa-dark: #075e54;
            --wa-teal: #128c7e;
            --wa-accent: #25d366;
            --wa-bg: #f0f2f5;
            --wa-chat-bg: #efeae2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--wa-bg);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar-wa {
            background: linear-gradient(135deg, var(--wa-dark), var(--wa-teal));
            padding: 8px 16px;
            color: white;
            z-index: 1030;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Nav Tabs Customization */
        .nav-tabs-wa {
            border-bottom: 2px solid #ddd;
            background: #fff;
        }

        .nav-tabs-wa .nav-link {
            color: #54656f;
            font-weight: 500;
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            padding: 12px 24px;
        }

        .nav-tabs-wa .nav-link:hover {
            border-color: transparent;
            color: var(--wa-teal);
        }

        .nav-tabs-wa .nav-link.active {
            color: var(--wa-primary);
            border-bottom-color: var(--wa-primary);
            background: transparent;
        }

        /* Content Areas */
        .tab-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 20px;
            overflow-y: auto;
            background: var(--wa-chat-bg);
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            background-size: contain;
            background-blend-mode: overlay;
            flex: 1;
        }

        /* Chat Bubbles */
        .msg-row {
            display: flex;
            margin-bottom: 8px;
        }

        .msg-row.out {
            justify-content: flex-end;
        }

        .msg-row.in {
            justify-content: flex-start;
        }

        .bubble {
            max-width: 65%;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .bubble.in {
            background: #fff;
            border-top-left-radius: 0;
        }

        .bubble.out {
            background: #d9fdd3;
            border-top-right-radius: 0;
        }

        .group-header-block {
            background: rgba(0, 0, 0, 0.03);
            padding: 6px;
            border-radius: 6px;
            margin-bottom: 6px;
            border-left: 3px solid var(--wa-teal);
            font-size: 12px;
        }

        .tiny-avatar {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 4px;
        }

        .sender-name {
            font-weight: 600;
            color: #1f2937;
        }

        .sender-phone {
            color: #6b7280;
            font-size: 11px;
        }

        .msg-text {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.4;
            color: #111b21;
        }

        .time {
            font-size: 11px;
            color: #667781;
            text-align: right;
            margin-top: 4px;
        }

        /* Media in Chat */
        .media-wrapper {
            position: relative;
            margin-bottom: 6px;
            border-radius: 6px;
            overflow: hidden;
            background: #e9edef;
            display: inline-block;
            max-width: 100%;
        }

        .msg-image,
        .msg-video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 6px;
            display: block;
        }

        .msg-sticker {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        .btn-save-media {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: transform 0.1s;
        }

        .btn-save-media:hover {
            transform: scale(1.1);
        }

        .btn-save-media.saved {
            background: #dcf8c6;
            cursor: default;
        }

        /* Offcanvas Sidebar */
        .offcanvas-wa {
            width: 350px !important;
            border-left: none;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.05);
        }

        /* Groups Table */
        .groups-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fff;
        }

        .jid-cell {
            font-family: monospace;
            color: var(--wa-primary);
            cursor: pointer;
            font-size: 13px;
        }

        .copy-icon {
            opacity: 0.5;
            font-size: 12px;
            margin-left: 4px;
        }

        /* Status msg inside forms */
        .status-msg {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        /* Audio flex */
        .msg-audio {
            height: 40px;
        }

        .btn-save-inline {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            background: #eee;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Documents */
        .msg-document {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
            max-width: 300px;
        }

        .msg-document:hover {
            background: rgba(0, 0, 0, 0.08);
        }

        .doc-icon {
            font-size: 24px;
        }

        .doc-info {
            flex: 1;
            min-width: 0;
        }

        .doc-name {
            font-weight: 500;
            font-size: 14px;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }

        .doc-meta {
            font-size: 12px;
            color: #667781;
        }
    </style>
</head>

<body>

    <!-- Hidden instance input for JS compatibility -->
    <input type="hidden" id="instance-selector" value="<?= htmlspecialchars($activeInstanceName)?>">

    <!-- Top Navbar -->
    <nav class="navbar-wa d-flex justify-content-between align-items-center shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <img id="topbar-avatar"
                src="https://ui-avatars.com/api/?name=<?= urlencode($activeInstanceName)?>&background=128c7e&color=fff"
                class="rounded-circle border border-white" width="40" height="40" alt="">
            <div>
                <h6 class="mb-0 fw-bold">
                    <?= htmlspecialchars($activeInstanceName)?>
                </h6>
                <small class="text-white-50" style="font-size: 12px;">Instância Conectada</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light text-success fw-semibold" data-bs-toggle="offcanvas"
                data-bs-target="#sidebarTools">
                <i class="bi bi-tools"></i> Ferramentas
            </button>
            <a href="instances.php" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-left"></i> Trocar
            </a>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-wrapper bg-white">
        <div class="d-flex flex-column w-100">

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs-wa px-3" id="mainTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active d-flex align-items-center gap-2" id="chat-tab" data-bs-toggle="tab"
                        data-bs-target="#tab-chat" type="button" role="tab">
                        <i class="bi bi-chat-dots"></i> Conversas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link d-flex align-items-center gap-2" id="groups-tab" data-bs-toggle="tab"
                        data-bs-target="#tab-groups" type="button" role="tab" onclick="loadGroups()">
                        <i class="bi bi-people"></i> Grupos
                    </button>
                </li>
            </ul>

            <!-- Tab Contents -->
            <div class="tab-content w-100 h-100" id="mainTabContent">

                <!-- Chat Feed -->
                <div class="tab-pane fade show active h-100" id="tab-chat" role="tabpanel">
                    <div class="chat-container" id="monitor">
                        <div class="text-center mt-5">
                            <div class="spinner-border text-success" role="status"></div>
                            <div class="mt-2 text-muted">Aguardando mensagens da API...</div>
                        </div>
                    </div>
                </div>

                <!-- Groups List -->
                <div class="tab-pane fade h-100" id="tab-groups" role="tabpanel">
                    <div class="groups-container">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0 text-dark fw-bold">Grupos da Instância</h4>
                            <button class="btn btn-outline-success btn-sm d-flex align-items-center gap-2"
                                id="btn-sync-groups" onclick="syncGroups()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar Grupos
                            </button>
                        </div>
                        <div id="sync-groups-status" class="status-msg mb-3"></div>
                        <div id="groups-list" class="table-responsive">
                            <div class="text-center text-muted py-5">Carregando grupos...</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Offcanvas Sidebar Tools -->
    <div class="offcanvas offcanvas-end offcanvas-wa" tabindex="-1" id="sidebarTools">
        <div class="offcanvas-header border-bottom bg-light">
            <h5 class="offcanvas-title fw-bold text-dark"><i class="bi bi-wrench-adjustable me-2"></i> Ferramentas</h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-3 bg-light">

            <!-- Send Message Tool -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-3">
                    <h6 class="card-title fw-bold text-success mb-3"><i class="bi bi-send me-1"></i> Enviar Mensagem
                    </h6>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Número Destino</label>
                        <input type="text" class="form-control form-control-sm" id="send-number"
                            placeholder="5511999999999">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Mensagem</label>
                        <textarea class="form-control form-control-sm" id="send-text" rows="2"
                            placeholder="Sua mensagem..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Agendamento</label>
                        <select class="form-select form-select-sm" id="msg-schedule">
                            <option value="now">🟢 Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+10min">Daqui a 10 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="+2hours">Daqui a 2 horas</option>
                            <option value="tomorrow_8">🌅 Amanhã cedo (8h)</option>
                            <option value="tomorrow_same">🔄 Amanhã neste horário</option>
                        </select>
                    </div>
                    <button class="btn btn-success btn-sm w-100 fw-semibold" id="btn-send"
                        onclick="sendMessage()">Enviar</button>
                    <div id="send-status" class="status-msg text-center mt-2"></div>
                </div>
            </div>

            <!-- Send Status Tool -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-3">
                    <h6 class="card-title fw-bold text-success mb-3"><i class="bi bi-camera me-1"></i> Enviar Status
                    </h6>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Formato</label>
                        <select class="form-select form-select-sm" id="status-type" onchange="toggleStatusFields()">
                            <option value="text">Texto</option>
                            <option value="image">Imagem</option>
                            <option value="video">Vídeo</option>
                            <option value="audio">Áudio</option>
                        </select>
                    </div>

                    <div id="status-text-fields" class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Fundo</label>
                            <select class="form-select form-select-sm" id="status-bg-color">
                                <option value="1">Amarelo 1</option>
                                <option value="4">Verde 1</option>
                                <option value="7">Azul 1</option>
                                <option value="10">Lilás 1</option>
                                <option value="14">Rosa 1</option>
                                <option value="19" selected>Cinza (padrão)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Estilo</label>
                            <select class="form-select form-select-sm" id="status-font">
                                <option value="0">Padrão</option>
                                <option value="1" selected>Estilo 1</option>
                                <option value="2">Estilo 2</option>
                            </select>
                        </div>
                    </div>

                    <div id="status-media-fields" class="mb-2 d-none">
                        <label class="form-label small text-muted mb-1">URL da Mídia</label>
                        <input type="url" class="form-control form-control-sm" id="status-file"
                            placeholder="https://...">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Texto/Legenda</label>
                        <textarea class="form-control form-control-sm" id="status-text" rows="2"
                            placeholder="O que você está pensando?"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Agendamento</label>
                        <select class="form-select form-select-sm" id="status-schedule">
                            <option value="now">🟢 Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="tomorrow_8">🌅 Amanhã 8h</option>
                        </select>
                    </div>

                    <button class="btn btn-success btn-sm w-100 fw-semibold" id="btn-send-status"
                        onclick="sendStatus()">Publicar Status</button>
                    <div id="status-send-status" class="status-msg text-center mt-2"></div>
                </div>
            </div>

            <!-- Schedules Viewer -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="card-title fw-bold text-success mb-0"><i class="bi bi-calendar-check me-1"></i>
                            Agendamentos</h6>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="loadSchedules()"><i
                                class="bi bi-arrow-clockwise"></i></button>
                    </div>
                    <div id="schedules-list" class="small text-muted mt-2">Clique em atualizar para listar.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App JavaScript -->
    <script>
        let lastLogsString = '';
        let logsInterval = null;

        function escapeHTML(str) { if (!str) return ''; return str.replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag])); }
        function formatTime(timestamp) { return new Date(timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function getDocIcon(mimetype, fileName) {
            const ext = (fileName || '').split('.').pop().toLowerCase();
            const m = (mimetype || '').toLowerCase();
            if (m.includes('pdf') || ext === 'pdf') return '📄';
            if (m.includes('spreadsheet') || m.includes('excel') || ['xls', 'xlsx', 'csv'].includes(ext)) return '📊';
            if (m.includes('presentation') || m.includes('powerpoint') || ['ppt', 'pptx'].includes(ext)) return '📑';
            if (m.includes('word') || m.includes('document') || ['doc', 'docx'].includes(ext)) return '📝';
            if (m.includes('zip') || m.includes('rar') || m.includes('compressed')) return '🗜️';
            if (m.includes('text')) return '📃';
            return '📎';
        }

        function toggleStatusFields() {
            const type = document.getElementById('status-type').value;
            const textFields = document.getElementById('status-text-fields');
            const mediaFields = document.getElementById('status-media-fields');
            if (type === 'text') {
                textFields.classList.remove('d-none');
                mediaFields.classList.add('d-none');
            } else {
                textFields.classList.add('d-none');
                mediaFields.classList.remove('d-none');
            }
        }

        async function sendMessage() {
            const activeInstance = document.getElementById('instance-selector').value;
            const number = document.getElementById('send-number').value.trim();
            const text = document.getElementById('send-text').value.trim();
            const schedule = document.getElementById('msg-schedule').value;
            const statusDiv = document.getElementById('send-status');
            const btn = document.getElementById('btn-send');

            if (!number) return alert("Insira o número destino.");
            if (!text) return alert("Insira a mensagem.");

            const isNow = schedule === 'now';
            const action = isNow ? 'send_message' : 'schedule_message';
            const msg = isNow ? 'Enviando...' : 'Agendando...';

            btn.disabled = true;
            statusDiv.innerHTML = `<span class="text-secondary">${msg}</span>`;

            try {
                const res = await fetch(`index.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ instance_name: activeInstance, number, text, schedule })
                });
                const data = await res.json();

                if (res.ok) {
                    if (isNow) {
                        statusDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Enviado!</span>';
                    } else {
                        statusDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Agendado para ${data.scheduled_at}</span>`;
                        loadSchedules();
                    }
                    document.getElementById('send-text').value = '';
                } else {
                    statusDiv.innerHTML = `<span class="text-danger">Erro: ${data.error || 'Falha'}</span>`;
                }
            } catch (e) {
                statusDiv.innerHTML = '<span class="text-danger">Falha de comunicação.</span>';
            } finally {
                btn.disabled = false;
            }
        }

        async function sendStatus() {
            const activeInstance = document.getElementById('instance-selector').value;
            const type = document.getElementById('status-type').value;
            const text = document.getElementById('status-text').value.trim();
            const schedule = document.getElementById('status-schedule').value;
            const statusDiv = document.getElementById('status-send-status');
            const btn = document.getElementById('btn-send-status');

            const payload = { instance_name: activeInstance, type, text, schedule };

            if (type === 'text') {
                payload.bg_color = document.getElementById('status-bg-color').value;
                payload.font = document.getElementById('status-font').value;
            } else {
                payload.file = document.getElementById('status-file').value.trim();
                if (!payload.file) return alert("Insira a URL do arquivo de mídia.");
            }

            if (type === 'text' && !text) return alert("Insira o texto do status.");

            const isNow = schedule === 'now';
            const action = isNow ? 'send_status' : 'schedule_status';
            const msg = isNow ? 'Publicando...' : 'Agendando...';

            btn.disabled = true;
            statusDiv.innerHTML = `<span class="text-secondary">${msg}</span>`;

            try {
                const res = await fetch(`index.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (res.ok) {
                    if (isNow) {
                        statusDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Status publicado!</span>';
                    } else {
                        statusDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Agendado para ${data.scheduled_at}</span>`;
                        loadSchedules();
                    }
                    document.getElementById('status-text').value = '';
                } else {
                    statusDiv.innerHTML = `<span class="text-danger">Erro: ${data.error || 'Falha'}</span>`;
                }
            } catch (e) {
                statusDiv.innerHTML = '<span class="text-danger">Falha de comunicação.</span>';
            } finally {
                btn.disabled = false;
            }
        }

        async function syncGroups() {
            const activeInstance = document.getElementById('instance-selector').value;
            const btn = document.getElementById('btn-sync-groups');
            const statusDiv = document.getElementById('sync-groups-status');

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Atualizando...';
            statusDiv.innerHTML = "";

            try {
                const res = await fetch('index.php?action=sync_groups', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ instance_name: activeInstance })
                });
                const data = await res.json();

                if (res.ok) {
                    statusDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> ${data.count} grupos sincronizados!</span>`;
                    loadGroups();
                } else {
                    statusDiv.innerHTML = `<span class="text-danger">Erro: ${data.error || 'Falha'}</span>`;
                }
            } catch (e) {
                statusDiv.innerHTML = '<span class="text-danger">Falha de comunicação.</span>';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Atualizar Grupos';
            }
        }

        async function loadGroups() {
            const activeInstance = document.getElementById('instance-selector').value;
            const listDiv = document.getElementById('groups-list');

            try {
                const res = await fetch(`index.php?action=get_groups&name=${activeInstance}`);
                const groups = await res.json();

                if (groups.length === 0) {
                    listDiv.innerHTML = '<div class="text-center text-muted py-5">Nenhum grupo encontrado.</div>';
                    return;
                }

                let html = `<table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>JID</th>
                            <th>Nome</th>
                            <th class="text-center">Membros</th>
                            <th class="text-center">Admin</th>
                            <th class="text-center">Avisos</th>
                        </tr>
                    </thead>
                    <tbody>`;

                groups.forEach(g => {
                    html += `<tr>
                        <td><span class="jid-cell" onclick="copyJid('${escapeHTML(g.jid)}')" title="Copiar">${escapeHTML(g.jid)} <i class="bi bi-copy copy-icon"></i></span></td>
                        <td><strong>${escapeHTML(g.name || 'Sem nome')}</strong>${g.description ? '<br><small class="text-muted text-truncate d-inline-block" style="max-width:200px;">' + escapeHTML(g.description) + '</small>' : ''}</td>
                        <td class="text-center align-middle">${g.participant_count}</td>
                        <td class="text-center align-middle"><span class="badge ${g.is_admin == 1 ? 'bg-success' : 'bg-secondary'}">${g.is_admin == 1 ? 'Sim' : 'Não'}</span></td>
                        <td class="text-center align-middle"><span class="badge ${g.is_announce == 1 ? 'bg-warning text-dark' : 'bg-info text-dark'}">${g.is_announce == 1 ? 'Só Admins' : 'Todos'}</span></td>
                    </tr>`;
                });

                html += '</tbody></table>';
                listDiv.innerHTML = html;

            } catch (e) {
                listDiv.innerHTML = '<div class="text-danger text-center py-4">Erro ao carregar grupos.</div>';
            }
        }

        async function loadSchedules() {
            const activeInstance = document.getElementById('instance-selector').value;
            const listDiv = document.getElementById('schedules-list');
            listDiv.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

            try {
                const res = await fetch(`index.php?action=get_schedules&name=${activeInstance}`);
                const schedules = await res.json();

                if (schedules.length === 0) {
                    listDiv.innerHTML = '<div class="text-muted text-center my-2">Nenhum agendamento.</div>';
                    return;
                }

                let html = '<ul class="list-group list-group-flush">';
                schedules.forEach(s => {
                    const statusClass = s.process_status === 'pending' ? 'bg-warning text-dark' : (s.process_status === 'success' ? 'bg-success' : 'bg-danger');
                    let target = s.task_type === 'status' ? 'Status' : 'Mensagem';
                    html += `<li class="list-group-item px-0 py-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong style="color:var(--wa-dark)">${target}</strong>
                            <span class="badge ${statusClass}">${s.process_status}</span>
                        </div>
                        <div class="text-muted" style="font-size:11px"><i class="bi bi-clock"></i> ${s.scheduled_at}</div>
                    </li>`;
                });
                html += '</ul>';
                listDiv.innerHTML = html;

            } catch (e) {
                listDiv.innerHTML = '<div class="text-danger text-center">Erro ao carregar agendamentos.</div>';
            }
        }

        function copyJid(jid) {
            navigator.clipboard.writeText(jid).then(() => {
                alert('JID Copiado!');
            });
        }

        async function saveMedia(btn) {
            if (btn.classList.contains('saved')) return;

            const fileUrl = btn.getAttribute('data-url');
            const fileName = btn.getAttribute('data-name') || '';
            const msgType = btn.getAttribute('data-type') || '';

            if (!fileUrl) return alert('URL do arquivo não disponível.');

            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            btn.disabled = true;

            try {
                const res = await fetch('index.php?action=save_media', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file_url: fileUrl, file_name: fileName, msg_type: msgType })
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    btn.innerHTML = '<i class="bi bi-check-lg"></i>';
                    btn.classList.add('saved');
                    btn.classList.replace('btn-outline-primary', 'btn-success');
                    btn.title = 'Salvo: ' + data.file_name;
                } else {
                    btn.innerHTML = '<i class="bi bi-x"></i>';
                    setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
                    alert('Erro ao salvar: ' + (data.error || 'Falha'));
                }
            } catch (e) {
                btn.innerHTML = '<i class="bi bi-x"></i>';
                setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
                alert('Falha de comunicação ao salvar.');
            }
        }

        async function downloadAndShowMedia(el, messageId, mediaType) {
            const activeInstance = document.getElementById('instance-selector').value;
            if (!messageId) return;

            el.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Carregando...';
            el.style.cursor = 'default';
            el.onclick = null;

            try {
                const res = await fetch('index.php?action=download_media_api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ instance_name: activeInstance, message_id: messageId })
                });
                const data = await res.json();

                if (res.ok && data.fileURL) {
                    if (mediaType === 'sticker') {
                        el.outerHTML = `<div class="media-wrapper"><img src="${data.fileURL}" class="msg-sticker" alt="Sticker"></div>`;
                    } else if (mediaType === 'audio') {
                        el.outerHTML = `<div style="display:flex;align-items:center;gap:6px;"><audio class="msg-audio" controls preload="metadata" style="flex:1"><source src="${data.fileURL}" type="audio/mpeg"><source src="${data.fileURL}" type="${data.mimetype || 'audio/ogg'}">Áudio</audio></div>`;
                    } else {
                        el.outerHTML = `<a href="${data.fileURL}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-download"></i> Baixar Mídia</a>`;
                    }
                } else {
                    el.innerHTML = '<span class="text-danger"><i class="bi bi-x"></i> Falha</span>';
                    setTimeout(() => { el.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Tentar novamente'; el.style.cursor = 'pointer'; el.onclick = () => downloadAndShowMedia(el, messageId, mediaType); }, 3000);
                }
            } catch (e) {
                el.innerHTML = '<span class="text-danger">Erro de conexão</span>';
                setTimeout(() => { el.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Tentar novamente'; el.style.cursor = 'pointer'; el.onclick = () => downloadAndShowMedia(el, messageId, mediaType); }, 3000);
            }
        }

        async function fetchLogs() {
            const activeInstance = document.getElementById('instance-selector').value;
            if (!activeInstance) return;

            try {
                const response = await fetch(`index.php?action=get_logs&name=${activeInstance}`);
                const textData = await response.text();

                if (textData === lastLogsString) return;
                lastLogsString = textData;

                const logs = JSON.parse(textData);
                const monitorDiv = document.getElementById('monitor');

                const messagesEvents = logs.filter(log => log && log.EventType === 'messages' || (log.data && log.data.EventType === 'messages'));

                if (messagesEvents.length === 0) {
                    monitorDiv.innerHTML = '<div class="text-center text-muted py-5">Nenhuma mensagem registrada.</div>';
                    return;
                }

                monitorDiv.innerHTML = '';

                messagesEvents.forEach(rawLog => {
                    const log = rawLog.data ? rawLog.data : rawLog;
                    if (!log.message) return;

                    const msg = log.message;
                    const chatInfo = log.chat || {};
                    const isFromMe = msg.fromMe;
                    const alignClass = isFromMe ? 'out' : 'in';
                    const time = formatTime(msg.messageTimestamp);

                    let mediaHtml = '';
                    const content = msg.content || {};
                    const fileURL = msg.fileURL || '';
                    const mimetype = (typeof content === 'object' ? content.Mimetype : '') || '';

                    const saveData = fileURL ? `data-url="${escapeHTML(fileURL)}"` : '';
                    const saveName = (typeof content === 'object' && content.FileName) ? `data-name="${escapeHTML(content.FileName)}"` : '';
                    const saveType = `data-type="${msg.messageType}"`;
                    const saveBtn = fileURL ? `<button class="btn-save-media" onclick="saveMedia(this)" ${saveData} ${saveName} ${saveType} title="Salvar no servidor"><i class="bi bi-save"></i></button>` : '';

                    if (msg.messageType === 'ImageMessage') {
                        if (fileURL) {
                            mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-image" alt="Imagem" loading="lazy" onclick="window.open('${fileURL}','_blank')"></div>`;
                        } else if (content.JPEGThumbnail) {
                            mediaHtml = `<img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image rounded shadow-sm" alt="Thumbnail">`;
                        }
                    } else if (msg.messageType === 'StickerMessage') {
                        if (fileURL) {
                            mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-sticker" alt="Sticker"></div>`;
                        } else {
                            const msgId = msg.Id || msg.id || msg.messageid || '';
                            mediaHtml = `<div class="bg-light p-3 rounded text-center border" style="cursor:pointer" onclick="downloadAndShowMedia(this, '${msgId}', 'sticker')"><i class="bi bi-emoji-smile fs-4 text-primary"></i><br><small>Ver Sticker</small></div>`;
                        }
                    } else if (msg.messageType === 'VideoMessage') {
                        if (fileURL) {
                            mediaHtml = `<div class="media-wrapper">${saveBtn}<video class="msg-video" controls preload="metadata" poster="${content.JPEGThumbnail ? 'data:image/jpeg;base64,' + content.JPEGThumbnail : ''}"><source src="${fileURL}" type="${mimetype || 'video/mp4'}">Vídeo</video></div>`;
                        } else if (content.JPEGThumbnail) {
                            mediaHtml = `<div style="position:relative;cursor:pointer" class="d-inline-block"><img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image rounded shadow-sm" alt="Vídeo"><div class="position-absolute top-50 start-50 translate-middle bg-dark bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center text-white" style="width:48px;height:48px"><i class="bi bi-play-fill fs-3"></i></div></div>`;
                        } else {
                            mediaHtml = `<div class="bg-light p-3 rounded text-center border"><i class="bi bi-film fs-4 text-primary"></i><br><small>Vídeo</small></div>`;
                        }
                    } else if (msg.messageType === 'AudioMessage' || msg.messageType === 'PTTMessage') {
                        if (fileURL) {
                            mediaHtml = `<div class="d-flex align-items-center gap-2 mb-2"><audio class="msg-audio flex-grow-1" controls preload="metadata"><source src="${fileURL}" type="audio/mpeg"><source src="${fileURL}" type="${mimetype || 'audio/ogg'}"></audio><button class="btn btn-sm btn-outline-secondary" onclick="saveMedia(this)" ${saveData} ${saveName} ${saveType} title="Salvar"><i class="bi bi-save"></i></button></div>`;
                        } else {
                            const msgId = msg.Id || msg.id || msg.messageid || '';
                            mediaHtml = `<div class="bg-light px-3 py-2 rounded text-center border" style="cursor:pointer" onclick="downloadAndShowMedia(this, '${msgId}', 'audio')"><i class="bi bi-play-circle text-primary me-2"></i><small>Ouvir ${msg.messageType === 'PTTMessage' ? 'Voz' : 'Áudio'}</small></div>`;
                        }
                    } else if (msg.messageType === 'DocumentMessage') {
                        const fileName = (typeof content === 'object' ? content.FileName : '') || 'documento';
                        const fileSize = (typeof content === 'object' ? content.FileLength : 0) || 0;
                        const sizeStr = fileSize > 0 ? formatFileSize(fileSize) : '';
                        const docIcon = getDocIcon(mimetype, fileName);
                        if (fileURL) {
                            mediaHtml = `<div class="msg-document mb-2"><span class="doc-icon">${docIcon}</span><div class="doc-info"><div class="doc-name text-truncate">${escapeHTML(fileName)}</div><div class="doc-meta">${escapeHTML(mimetype)}${sizeStr ? ' • ' + sizeStr : ''}</div></div><a href="${fileURL}" class="btn btn-sm btn-light" download><i class="bi bi-download"></i></a><button class="btn btn-sm btn-light ms-1" onclick="saveMedia(this)" ${saveData} data-name="${escapeHTML(fileName)}" ${saveType} title="Salvar"><i class="bi bi-save"></i></button></div>`;
                        } else {
                            mediaHtml = `<div class="msg-document mb-2"><span class="doc-icon">${docIcon}</span><div class="doc-info"><div class="doc-name text-truncate">${escapeHTML(fileName)}</div><div class="doc-meta">${escapeHTML(mimetype)}${sizeStr ? ' • ' + sizeStr : ''}</div></div></div>`;
                        }
                    } else if (msg.messageType === 'GifMessage') {
                        if (fileURL) {
                            mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-image" alt="GIF" loading="lazy"></div>`;
                        } else if (content.JPEGThumbnail) {
                            mediaHtml = `<div class="position-relative d-inline-block"><img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image rounded shadow-sm" alt="GIF"><span class="badge bg-dark position-absolute bottom-0 start-0 m-2 opacity-75">GIF</span></div>`;
                        }
                    }

                    let senderName = '';
                    let senderPhone = '';
                    let headerHtml = '';

                    if (msg.isGroup) {
                        const groupImage = chatInfo.imagePreview || 'https://ui-avatars.com/api/?name=G&background=dfe5e7&color=667781';
                        const groupName = chatInfo.name || msg.groupName || "Grupo";
                        senderName = isFromMe ? "Você" : (msg.senderName || "Desconhecido");
                        senderPhone = isFromMe ? activeInstance : (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');

                        headerHtml = `
                            <div class="group-header-block">
                                <div class="d-flex align-items-center mb-1">
                                    <img src="${groupImage}" class="tiny-avatar shadow-sm" alt="G">
                                    <span class="fw-bold text-dark">${escapeHTML(groupName)}</span>
                                </div>
                                <div>
                                    <span class="sender-name">${escapeHTML(senderName)}</span>
                                    <span class="sender-phone ms-1">${senderPhone}</span>
                                </div>
                            </div>
                        `;
                    } else if (!isFromMe) {
                        const contactName = msg.senderName || chatInfo.name || "Contato";
                        const contactPhone = chatInfo.phone || (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');
                        headerHtml = `<div class="mb-1"><span class="fw-bold text-dark">${escapeHTML(contactName)}</span> ${contactPhone ? `<span class="sender-phone ms-1">${contactPhone}</span>` : ''}</div>`;
                    }

                    let html = `
                        <div class="msg-row ${alignClass}">
                            <div class="bubble ${alignClass}">
                                ${headerHtml}
                                ${mediaHtml}
                                <div class="msg-text">${escapeHTML(msg.text || '')}</div>
                                <div class="time">${time} ${isFromMe ? '<i class="bi bi-check2-all text-info"></i>' : ''}</div>
                            </div>
                        </div>
                    `;
                    monitorDiv.innerHTML += html;
                });

                monitorDiv.scrollTop = monitorDiv.scrollHeight;
            } catch (error) { console.error("Erro:", error); }
        }

        // Initialize Logs polling
        if (document.getElementById('instance-selector').value) {
            fetchLogs();
            logsInterval = setInterval(fetchLogs, 2000);
            loadSchedules();
        }
    </script>
</body>

</html>