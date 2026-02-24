let lastLogsString = '';

// toggleSidebar handled by Bootstrap offcanvas
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

// --- TABS ---
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');

    if (tabId === 'groups') loadGroups();
    if (tabId === 'schedules') loadSchedules();
}

// --- TOGGLE STATUS FIELDS ---
function toggleStatusFields() {
    const type = document.getElementById('status-type').value;
    const textSettings = document.getElementById('status-text-settings');
    const mediaContainer = document.getElementById('status-media-container');

    if (textSettings) textSettings.style.display = type === 'text' ? 'flex' : 'none';
    if (mediaContainer) mediaContainer.style.display = type === 'text' ? 'none' : 'block';
}

// --- DADOS DE INSTÂNCIAS (cache local) ---
let instancesData = [];
let activeInstanceName = '';
let logsInterval = null;

// --- RENDERIZAR CARDS DE INSTÂNCIAS ---
function renderInstanceCards(instances) {
    const grid = document.getElementById('instances-grid');
    if (instances.length === 0) {
        grid.innerHTML = '<div style="text-align:center;color:#999;grid-column:1/-1;padding:40px;">Nenhuma instância encontrada.<br>Clique em "Atualizar Instâncias" para sincronizar.</div>';
        return;
    }

    let html = '';
    instances.forEach(inst => {
        const pic = inst.profile_pic_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(inst.profile_name || inst.name)}&background=128c7e&color=fff&size=52`;
        const statusBadge = inst.status === 'connected'
            ? '<span class="badge badge-connected">🟢 Online</span>'
            : '<span class="badge badge-disconnected">🔴 Offline</span>';
        const bizBadge = inst.is_business == 1 ? '<span class="badge badge-business">💼 Business</span>' : '';
        const phone = inst.phone_number ? `+${inst.phone_number}` : '';
        const profileName = inst.profile_name || '';

        html += `<div class="instance-card" onclick="selectInstance('${escapeHTML(inst.name)}')">
                    <img class="card-avatar" src="${pic}" alt="" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(inst.name)}&background=128c7e&color=fff'">
                    <div class="card-info">
                        <div class="card-name">${escapeHTML(inst.name)}</div>
                        ${profileName ? `<div class="card-profile">${escapeHTML(profileName)}</div>` : ''}
                        ${phone ? `<div class="card-phone">📞 ${phone}</div>` : ''}
                        <div class="card-badges">${statusBadge}${bizBadge}</div>
                    </div>
                </div>`;
    });
    grid.innerHTML = html;
}

// --- CARREGAR INSTÂNCIAS (para tela de seleção) ---
async function loadInstances() {
    try {
        const res = await fetch('api.php?action=get_instances');
        instancesData = await res.json();
        renderInstanceCards(instancesData);
    } catch (e) {
        document.getElementById('instances-grid').innerHTML = '<div style="color:red;text-align:center;grid-column:1/-1;padding:20px;">Erro ao carregar instâncias.</div>';
    }
}

// --- SINCRONIZAR + REFRESH ---
async function syncAndRefresh() {
    const btn = document.getElementById('btn-sync-select');
    btn.disabled = true;
    btn.innerText = '⏳ Sincronizando...';

    try {
        const res = await fetch('api.php?action=sync_instances');
        const data = await res.json();
        if (res.ok) {
            await loadInstances();
        }
    } catch (e) {
        // silently fail
    } finally {
        btn.disabled = false;
        btn.innerText = '🔄 Atualizar Instâncias';
    }
}

// --- INICIALIZAÇÃO DO FEED ---
async function initFeed() {
    try {
        const res = await fetch('api.php?action=get_instances');
        instancesData = await res.json();
        const instanceNameInput = document.getElementById('instance-selector');
        const instanceName = instanceNameInput ? instanceNameInput.value : '';
        if (instanceName) {

            if (document.getElementById('local-schedules-list')) {
                const path = window.location.pathname;
                if (path.includes('send_message')) loadLocalSchedules('message');
                if (path.includes('send_status')) loadLocalSchedules('status');
            }

            selectInstance(instanceName);
        }
    } catch (e) {
        console.error("Erro ao carregar metadados da instância.");
    }
}

// --- SELECIONAR INSTÂNCIA (ir para feed) ---
function selectInstance(name) {
    activeInstanceName = name;
    const inst = instancesData.find(i => i.name === name) || {};

    // Preencher top bar
    const pic = inst.profile_pic_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(inst.profile_name || name)}&background=128c7e&color=fff`;
    document.getElementById('topbar-avatar').src = pic;
    document.getElementById('topbar-name').textContent = inst.profile_name || name;
    document.getElementById('topbar-phone').textContent = inst.phone_number ? `+${inst.phone_number} • ${name}` : name;

    // Setar hidden selector pra compatibilidade
    const selector = document.getElementById('instance-selector');
    selector.innerHTML = `<option value="${escapeHTML(name)}">${escapeHTML(name)}</option>`;
    selector.value = name;

    // Trocar telas
    // document.getElementById('screen-select').classList.add('hidden');
    // document.getElementById('screen-feed').classList.remove('hidden');

    // Limpar e carregar
    const mon = document.getElementById('monitor'); if (mon) mon.innerHTML = '<div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Carregando mensagens...</div>';
    lastLogsString = '';
    fetchLogs();

    // Iniciar polling
    if (logsInterval) clearInterval(logsInterval);
    logsInterval = setInterval(fetchLogs, 2000);
}

// --- TROCAR DE INSTÂNCIA (voltar para seleção) ---
function switchInstance() {
    window.location.href = 'instances.php';
}

function changeInstance() {
    // backward compat — no-op since we use selectInstance now
}

// --- ENVIAR MENSAGEM ---
async function sendMessage() {
    const activeInstance = document.getElementById('instance-selector').value;
    if (!activeInstance) return alert("Selecione uma instância no topo primeiro.");

    const number = document.getElementById('msg-number').value.trim();
    const text = document.getElementById('msg-text').value.trim();
    const schedule = document.getElementById('msg-schedule').value;
    const statusDiv = document.getElementById('msg-status');

    if (!number || !text) return alert("Preencha número e mensagem.");

    const action = schedule === 'now' ? 'send_message' : 'schedule_message';
    const payload = { instance_name: activeInstance, number, text };
    if (schedule !== 'now') {
        payload.schedule = schedule;
    }

    statusDiv.innerHTML = schedule === 'now' ? 'Enviando...' : 'Agendando...';

    try {
        const res = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.ok) {
            statusDiv.innerHTML = schedule === 'now' ? '<span style="color: green;">✔ Enviado!</span>' : '<span style="color: green;">✔ Agendado!</span>';
            document.getElementById('msg-text').value = '';
            document.getElementById('msg-schedule').value = 'now';
        } else {
            const data = await res.json().catch(() => ({}));
            statusDiv.innerHTML = `<span style="color: red;">Erro: ${data.error || 'Falha no envio'}</span>`;
        }
    } catch (error) {
        statusDiv.innerHTML = '<span style="color: red;">Erro de conexão.</span>';
    }
}

// --- ENVIAR STATUS/STORIES ---
async function sendStatus() {
    const activeInstance = document.getElementById('instance-selector').value;
    if (!activeInstance) return alert("Selecione uma instância no topo primeiro.");

    const type = document.getElementById('status-type').value;
    const text = document.getElementById('status-text').value.trim();
    const statusDiv = document.getElementById('status-feedback');

    const payload = { instance_name: activeInstance, type, text };

    if (type === 'text') {
        payload.bg_color = document.getElementById('status-bg').value;
        payload.font = document.getElementById('status-font').value;
    } else {
        payload.file = document.getElementById('status-file').value.trim();
        if (!payload.file) return alert("Insira a URL do arquivo de mídia.");
    }

    if (type === 'text' && !text) return alert("Insira o texto do status.");

    const schedule = document.getElementById('status-schedule').value;
    const action = schedule === 'now' ? 'send_status' : 'schedule_status';
    if (schedule !== 'now') {
        payload.schedule = schedule;
    }

    statusDiv.style.display = 'block';
    statusDiv.innerHTML = schedule === 'now' ? 'Publicando...' : 'Agendando...';
    try {
        const res = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.ok) {
            statusDiv.innerHTML = schedule === 'now' ? '<span style="color: green;">✔ Status publicado!</span>' : '<span style="color: green;">✔ Status agendado!</span>';
            document.getElementById('status-text').value = '';
            document.getElementById('status-schedule').value = 'now';
            if (schedule !== 'now') loadLocalSchedules('status');
        } else {
            const err = await res.json();
            statusDiv.innerHTML = `<span style="color: red;">Erro: ${err.error || 'Falha no envio'}</span>`;
        }
    } catch (e) {
        statusDiv.innerHTML = '<span style="color: red;">Falha de comunicação.</span>';
    }
}

// --- SINCRONIZAR GRUPOS ---
async function syncGroups() {
    const activeInstance = document.getElementById('instance-selector').value;
    if (!activeInstance) return alert("Selecione uma instância no topo primeiro.");

    const btn = document.getElementById('btn-sync-groups');
    const statusDiv = document.getElementById('sync-groups-status');

    btn.disabled = true;
    btn.innerText = "Atualizando...";
    statusDiv.innerHTML = "";

    try {
        const res = await fetch('api.php?action=sync_groups', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ instance_name: activeInstance })
        });
        const data = await res.json();

        if (res.ok) {
            statusDiv.innerHTML = `<span style="color: green;">✔ ${data.count} grupos sincronizados!</span>`;
            loadGroups();
        } else {
            statusDiv.innerHTML = `<span style="color: red;">Erro: ${data.error || 'Falha'}</span>`;
        }
    } catch (e) {
        statusDiv.innerHTML = '<span style="color: red;">Falha de comunicação.</span>';
    } finally {
        btn.disabled = false;
        btn.innerText = "🔄 Atualizar Grupos";
    }
}

// --- CARREGAR GRUPOS ---
async function loadGroups() {
    const activeInstance = document.getElementById('instance-selector').value;
    const listDiv = document.getElementById('groups-list');
    if (!activeInstance) {
        listDiv.innerHTML = '<div style="text-align: center; color: #666; font-size: 13px; padding: 30px 0;">Selecione uma instância primeiro</div>';
        return;
    }

    try {
        const res = await fetch(`api.php?action=get_groups&name=${activeInstance}`);
        const groups = await res.json();

        if (groups.length === 0) {
            listDiv.innerHTML = '<div style="text-align: center; color: #666; font-size: 13px; padding: 30px 0;">Nenhum grupo encontrado. Clique em "Atualizar Grupos".</div>';
            return;
        }

        let html = `<table class="groups-table">
                    <thead>
                        <tr>
                            <th>JID (clique p/ copiar)</th>
                            <th>Nome</th>
                            <th>Participantes</th>
                            <th>Admin?</th>
                            <th>Anúncio</th>
                        </tr>
                    </thead>
                    <tbody>`;

        groups.forEach(g => {
            html += `<tr>
                        <td><span class="jid-cell" onclick="copyJid('${escapeHTML(g.jid)}')" title="Clique para copiar">${escapeHTML(g.jid)} <span class="copy-icon">📋</span></span></td>
                        <td><strong>${escapeHTML(g.name || 'Sem nome')}</strong>${g.description ? '<br><small style="color:#667781;">' + escapeHTML(g.description).substring(0, 60) + '</small>' : ''}</td>
                        <td style="text-align:center;">${g.participant_count}</td>
                        <td style="text-align:center;"><span class="${g.is_admin == 1 ? 'text-success fw-bold' : 'text-danger fw-bold'}">${g.is_admin == 1 ? '✅ Sim' : '❌ Não'}</span></td>
                        <td style="text-align:center;"><span class="${g.is_announce == 1 ? 'text-success fw-bold' : 'text-danger fw-bold'}">${g.is_announce == 1 ? 'Só Admins' : 'Todos'}</span></td>
                    </tr>`;
        });

        html += '</tbody></table>';
        listDiv.innerHTML = html;

    } catch (e) {
        listDiv.innerHTML = '<div style="text-align: center; color: red; font-size: 13px; padding: 30px 0;">Erro ao carregar grupos.</div>';
    }
}

// --- CARREGAR E EXCLUIR AGENDAMENTOS ---
async function loadSchedules() {
    const activeInstance = document.getElementById('instance-selector').value;
    const listDiv = document.getElementById('schedules-list');

    if (!activeInstance) {
        listDiv.innerHTML = '<div style="text-align: center; color: #666; font-size: 13px; padding: 30px 0;">Selecione uma instância primeiro</div>';
        return;
    }

    try {
        listDiv.innerHTML = '<div class="text-center text-muted py-5 bg-white border rounded"><i class="bi bi-arrow-repeat fs-1 d-block mb-2 text-info spinner-border border-0"></i> Carregando agendamentos...</div>';

        const res = await fetch(`api.php?action=get_schedules&name=${encodeURIComponent(activeInstance)}`);
        const schedules = await res.json();

        if (!Array.isArray(schedules) || schedules.length === 0) {
            listDiv.innerHTML = '<div style="text-align: center; color: #666; font-size: 13px; padding: 30px 0;">Nenhum agendamento ativo.</div>';
            return;
        }

        let html = `<table class="groups-table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Tipo</th>
                            <th>Agendado Para</th>
                            <th>Payload</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>`;

        schedules.forEach(s => {
            let statusBadge = s.status === 'pending' ? '<span class="badge bg-warning text-dark">Pendente</span>' :
                (s.status === 'success' || s.status === 'sent' ? '<span class="badge bg-success">Enviado</span>' :
                    (s.status === 'cancelled' ? '<span class="badge bg-secondary">Cancelado</span>' :
                        (s.status === 'paused' ? '<span class="badge bg-info text-dark">Aguardando Revisão</span>' : '<span class="badge bg-danger">Falhou</span>')));

            let payloadDisplay = escapeHTML(s.payload);
            try {
                const parsed = JSON.parse(s.payload);
                if (s.task_type === 'message' && parsed.text) {
                    payloadDisplay = '<strong>Para: </strong>' + escapeHTML(parsed.number || '') + '<br><div class="text-muted mt-1" style="font-size:0.9em;">' + escapeHTML(parsed.text).replace(/\\n/g, '<br>') + '</div>';
                } else if (s.task_type === 'status' && parsed.text) {
                    payloadDisplay = '<strong>Status:</strong><br><div class="text-muted mt-1" style="font-size:0.9em;">' + escapeHTML(parsed.text).replace(/\\n/g, '<br>') + '</div>';
                }
            } catch (e) { }

            html += `<tr>
                        <td style="text-align:center;">${s.id}</td>
                        <td style="text-align:center;">${statusBadge}</td>
                        <td style="text-align:center;">${escapeHTML(s.task_type)}</td>
                        <td>${new Date(s.scheduled_at).toLocaleString()}</td>
                        <td style="white-space: normal; word-wrap: break-word; min-width: 300px; max-width: 500px;">${payloadDisplay}</td>
                        <td style="text-align:center; min-width: 100px;">
                            ${s.status === 'paused' ? `<button class="btn btn-sm btn-success shadow-sm me-1" title="Aprovar Mensagem" onclick="approveSchedule(${s.id})"><i class="bi bi-check2"></i></button>` : ''}
                            ${s.status === 'pending' || s.status === 'paused' ? `<button class="btn btn-sm btn-outline-danger shadow-sm" title="Excluir Agendamento" onclick="deleteSchedule(${s.id})"><i class="bi bi-trash"></i></button>` : ''}
                        </td>
                    </tr>`;
        });

        html += '</tbody></table>';
        listDiv.innerHTML = html;

    } catch (e) {
        listDiv.innerHTML = '<div style="text-align: center; color: red; font-size: 13px; padding: 30px 0;">Erro ao carregar agendamentos.</div>';
    }
}

async function deleteSchedule(id) {
    if (!confirm('Tem certeza que deseja cancelar este agendamento?')) return;

    try {
        const res = await fetch('api.php?action=cancel_schedule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const data = await res.json();
        if (data.success) {
            alert('Agendamento cancelado com sucesso!');
            loadSchedules();
        } else {
            alert('Erro ao cancelar agendamento.');
        }
    } catch (e) {
        alert('Falha de conexão ao cancelar agendamento.');
    }
}

function copyJid(jid) {
    navigator.clipboard.writeText(jid).then(() => {
        const old = event.target.closest('.jid-cell');
        old.style.color = '#16a34a';
        setTimeout(() => old.style.color = '#00a884', 1500);
    });
}

// --- SALVAR MÍDIA NO SERVIDOR ---
async function saveMedia(btn) {
    if (btn.classList.contains('saved')) return;

    const fileUrl = btn.getAttribute('data-url');
    const fileName = btn.getAttribute('data-name') || '';
    const msgType = btn.getAttribute('data-type') || '';

    if (!fileUrl) return alert('URL do arquivo não disponível.');

    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳';
    btn.disabled = true;

    try {
        const res = await fetch('api.php?action=save_media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_url: fileUrl, file_name: fileName, msg_type: msgType })
        });
        const data = await res.json();

        if (res.ok && data.success) {
            btn.innerHTML = '✅';
            btn.classList.add('saved');
            btn.title = 'Salvo: ' + data.file_name;
        } else {
            btn.innerHTML = '❌';
            setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
            alert('Erro ao salvar: ' + (data.error || 'Falha'));
        }
    } catch (e) {
        btn.innerHTML = '❌';
        setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
        alert('Falha de comunicação ao salvar.');
    }
}

// --- DOWNLOAD SOB DEMANDA (stickers/áudios sem fileURL) ---
async function downloadAndShowMedia(el, messageId, mediaType) {
    const activeInstance = document.getElementById('instance-selector').value;
    if (!activeInstance || !messageId) return;

    el.innerHTML = '<span>⏳</span>Carregando...';
    el.style.cursor = 'default';
    el.onclick = null;

    try {
        const res = await fetch('api.php?action=download_media_api', {
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
                el.outerHTML = `<a href="${data.fileURL}" target="_blank">📥 Baixar mídia</a>`;
            }
        } else {
            el.innerHTML = '<span>❌</span>Falha ao carregar';
            setTimeout(() => { el.innerHTML = '<span>🔄</span>Tentar novamente'; el.style.cursor = 'pointer'; el.onclick = () => downloadAndShowMedia(el, messageId, mediaType); }, 3000);
        }
    } catch (e) {
        el.innerHTML = '<span>❌</span>Erro de conexão';
        setTimeout(() => { el.innerHTML = '<span>🔄</span>Tentar novamente'; el.style.cursor = 'pointer'; el.onclick = () => downloadAndShowMedia(el, messageId, mediaType); }, 3000);
    }
}

// --- RENDERIZAÇÃO DA CONVERSA ---
async function fetchLogs() {
    const activeInstance = document.getElementById('instance-selector').value;
    if (!activeInstance) return;

    try {
        const response = await fetch(`api.php?action=get_logs&name=${activeInstance}`);
        const textData = await response.text();

        if (textData === lastLogsString) return;
        lastLogsString = textData;

        const logs = JSON.parse(textData);
        const monitorDiv = document.getElementById('monitor');

        const messagesEvents = logs.filter(log => log && log.EventType === 'messages' || (log.data && log.data.EventType === 'messages'));

        if (messagesEvents.length === 0) {
            monitorDiv.innerHTML = '<div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Nenhuma mensagem registrada nesta instância.</div>';
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
            const saveBtn = fileURL ? `<button class="btn-save-media" onclick="saveMedia(this)" ${saveData} ${saveName} ${saveType} title="Salvar no servidor">💾</button>` : '';

            if (msg.messageType === 'ImageMessage') {
                if (fileURL) {
                    mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-image" alt="Imagem" loading="lazy" onclick="window.open('${fileURL}','_blank')"></div>`;
                } else if (content.JPEGThumbnail) {
                    mediaHtml = `<img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image" alt="Imagem (miniatura)">`;
                }
            } else if (msg.messageType === 'StickerMessage') {
                if (fileURL) {
                    mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-sticker" alt="Sticker"></div>`;
                } else {
                    const msgId = msg.Id || msg.id || msg.messageid || '';
                    mediaHtml = `<div class="media-placeholder" style="cursor:pointer" onclick="downloadAndShowMedia(this, '${msgId}', 'sticker')" title="Clique para carregar sticker"><span>🎨</span>Clique para ver sticker</div>`;
                }
            } else if (msg.messageType === 'VideoMessage') {
                if (fileURL) {
                    mediaHtml = `<div class="media-wrapper">${saveBtn}<video class="msg-video" controls preload="metadata" poster="${content.JPEGThumbnail ? 'data:image/jpeg;base64,' + content.JPEGThumbnail : ''}"><source src="${fileURL}" type="${mimetype || 'video/mp4'}">Vídeo</video></div>`;
                } else if (content.JPEGThumbnail) {
                    mediaHtml = `<div style="position:relative;cursor:pointer" title="Vídeo"><img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image" alt="Vídeo"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.6);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;color:white;font-size:18px">▶</div></div>`;
                } else {
                    mediaHtml = `<div class="media-placeholder"><span>🎬</span>Vídeo</div>`;
                }
            } else if (msg.messageType === 'AudioMessage' || msg.messageType === 'PTTMessage') {
                if (fileURL) {
                    mediaHtml = `<div style="display:flex;align-items:center;gap:6px;"><audio class="msg-audio" controls preload="metadata" style="flex:1"><source src="${fileURL}" type="audio/mpeg"><source src="${fileURL}" type="${mimetype || 'audio/ogg'}">Áudio</audio><button class="btn-save-inline" onclick="saveMedia(this)" ${saveData} ${saveName} ${saveType} title="Salvar no servidor">💾</button></div>`;
                } else {
                    const msgId = msg.Id || msg.id || msg.messageid || '';
                    mediaHtml = `<div class="media-placeholder" style="cursor:pointer" onclick="downloadAndShowMedia(this, '${msgId}', 'audio')" title="Clique para carregar áudio"><span>🎵</span>Clique para ouvir${msg.messageType === 'PTTMessage' ? ' (voz)' : ''}</div>`;
                }
            } else if (msg.messageType === 'DocumentMessage') {
                const fileName = (typeof content === 'object' ? content.FileName : '') || 'documento';
                const fileSize = (typeof content === 'object' ? content.FileLength : 0) || 0;
                const sizeStr = fileSize > 0 ? formatFileSize(fileSize) : '';
                const docIcon = getDocIcon(mimetype, fileName);
                if (fileURL) {
                    mediaHtml = `<a href="${fileURL}" target="_blank" class="msg-document" download><span class="doc-icon">${docIcon}</span><div class="doc-info"><div class="doc-name">${escapeHTML(fileName)}</div><div class="doc-meta">${escapeHTML(mimetype)}${sizeStr ? ' • ' + sizeStr : ''}</div></div></a><button class="btn-save-inline" onclick="event.preventDefault();saveMedia(this)" ${saveData} data-name="${escapeHTML(fileName)}" ${saveType} title="Salvar no servidor">💾 Salvar</button>`;
                } else {
                    mediaHtml = `<div class="msg-document"><span class="doc-icon">${docIcon}</span><div class="doc-info"><div class="doc-name">${escapeHTML(fileName)}</div><div class="doc-meta">${escapeHTML(mimetype)}${sizeStr ? ' • ' + sizeStr : ''}</div></div></div>`;
                }
            } else if (msg.messageType === 'GifMessage') {
                if (fileURL) {
                    mediaHtml = `<div class="media-wrapper">${saveBtn}<img src="${fileURL}" class="msg-image" alt="GIF" loading="lazy"></div>`;
                } else if (content.JPEGThumbnail) {
                    mediaHtml = `<div style="position:relative"><img src="data:image/jpeg;base64,${content.JPEGThumbnail}" class="msg-image" alt="GIF"><div style="position:absolute;bottom:8px;left:8px;background:rgba(0,0,0,0.6);color:white;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold">GIF</div></div>`;
                }
            }

            let headerHtml = '';
            if (msg.isGroup) {
                const groupImage = chatInfo.imagePreview || 'https://ui-avatars.com/api/?name=G&background=dfe5e7&color=667781';
                const groupName = chatInfo.name || msg.groupName || "Grupo";
                const senderName = isFromMe ? "Você" : (msg.senderName || "Desconhecido");
                const senderPhone = (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');

                headerHtml = `
                            <div class="group-header-block" style="margin-bottom: 8px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px;">
                                <div class="info-line" style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                    <img src="${groupImage}" style="width: 16px; height: 16px; object-fit: cover; border-radius: 50%;">
                                    <strong style="color: #4a5568;">${escapeHTML(groupName)}</strong>
                                </div>
                                <div class="info-line" style="font-size: 11px; color: #718096;">
                                    <span>${escapeHTML(senderName)} ${senderPhone ? `(${senderPhone})` : ''}</span>
                                </div>
                            </div>
                        `;
            } else if (!isFromMe) {
                const contactName = msg.senderName || chatInfo.name || "Contato";
                const contactPhone = chatInfo.phone || (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');
                headerHtml = `<div style="margin-bottom: 5px; font-weight: bold; color: var(--wa-teal); font-size: 13px;">${escapeHTML(contactName)} ${contactPhone ? `<span style="font-weight: normal; color: #a0aec0;">(${contactPhone})</span>` : ''}</div>`;
            }

            let html = `
                        <div class="msg-row ${alignClass}">
                            <div class="bubble ${alignClass}">
                                ${headerHtml}
                                ${mediaHtml}
                                <div class="msg-text">${escapeHTML(msg.text || '')}</div>
                                <div class="time" style="margin-top: 5px; font-size: 11px; color: rgba(0,0,0,0.45); display: flex; justify-content: flex-end; align-items: center; gap: 4px;">
                                    ${time} ${isFromMe ? '<span style="color: #53bdeb; font-size: 14px;">✓✓</span>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
            monitorDiv.innerHTML += html;
        });

        monitorDiv.scrollTop = monitorDiv.scrollHeight;
    } catch (error) {
        console.error("Erro:", error);
    }
}

// Initialize Feed
initFeed();


// --- CARREGAR AGENDAMENTOS LOCAIS (send_message / send_status) ---
async function loadLocalSchedules(type) {
    const activeInstance = document.getElementById('instance-selector').value;
    const listDiv = document.getElementById('local-schedules-list');
    if (!listDiv) return;

    if (!activeInstance) {
        listDiv.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Selecione uma instância.</td></tr>';
        return;
    }

    try {
        listDiv.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Carregando...</td></tr>';
        const res = await fetch(`api.php?action=get_schedules&name=${encodeURIComponent(activeInstance)}`);
        const schedules = await res.json();
        const filtered = (schedules || []).filter(s => s.task_type === type);

        if (filtered.length === 0) {
            listDiv.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Nenhum agendamento ativo.</td></tr>';
            return;
        }

        let html = '';
        filtered.forEach(s => {
            let statusBadge = s.status === 'pending' ? '<span class="badge bg-warning text-dark">Pendente</span>' :
                (s.status === 'success' || s.status === 'sent' ? '<span class="badge bg-success">Enviado</span>' :
                    (s.status === 'cancelled' ? '<span class="badge bg-secondary">Cancelado</span>' : '<span class="badge bg-danger">Falhou</span>'));

            let destOrType = 'N/A';
            try {
                const p = JSON.parse(s.payload);
                if (type === 'message') destOrType = p.number || 'N/A';
                if (type === 'status') destOrType = p.type || 'N/A';
            } catch (e) { }

            html += `<tr>
                <td class="px-4">${new Date(s.scheduled_at).toLocaleString()}</td>
                <td>${escapeHTML(destOrType)}</td>
                <td>${statusBadge}</td>
                <td class="text-end px-4">
                    ${s.status === 'pending' ? `<button class="btn btn-sm btn-outline-danger" title="Cancelar Agendamento" onclick="deleteSchedule(${s.id})"><i class="bi bi-trash"></i></button>` : ''}
                </td>
            </tr>`;
        });
        listDiv.innerHTML = html;
    } catch (e) {
        listDiv.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Erro ao carregar agendamentos.</td></tr>';
    }
}

let loadedAgents = [];

// --- AGENTES DE IA (OpenCode) ---
async function loadAgents() {
    const listDiv = document.getElementById('agents-list');
    if (!listDiv) return;

    listDiv.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted small"><i class="bi bi-arrow-repeat text-warning fs-3 d-block mb-2 spinner-border border-0"></i>Carregando agentes...</td></tr>';

    try {
        const res = await fetch('api.php?action=get_agents');
        const agents = await res.json();
        loadedAgents = agents;

        if (!Array.isArray(agents) || agents.length === 0) {
            listDiv.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted small">Nenhum agente configurado. Clique em "Novo Agente" para começar.</td></tr>';
            return;
        }

        let html = '';
        agents.forEach(a => {
            const statusBadge = a.status === 'active'
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="bi bi-circle-fill small me-1" style="font-size:8px;"></i>Ativo</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="bi bi-pause-circle small me-1"></i>Pausado</span>';

            const reviewBadge = a.requires_review == 1
                ? '<span class="text-warning fw-bold"><i class="bi bi-shield-check me-1"></i>Sim</span>'
                : '<span class="text-success"><i class="bi bi-send-check me-1"></i>Não</span>';

            html += `
                <tr>
                    <td class="px-3 py-3">
                        <div class="fw-bold text-dark">${escapeHTML(a.name)}</div>
                        <div class="text-muted small" style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHTML(a.prompt)}">
                            <i class="bi bi-chat-quote me-1"></i>${escapeHTML(a.prompt)}
                        </div>
                    </td>
                    <td class="fw-semibold text-secondary"><i class="bi bi-person me-1"></i>${escapeHTML(a.recipient)}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><i class="bi bi-clock-history me-1"></i>${a.interval_minutes}m</span>
                    </td>
                    <td class="text-center">${reviewBadge}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-end px-3">
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-sm btn-light border text-primary" title="Editar Agente" onclick="openEditAgentModal(${a.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-light border text-info" title="Forçar Geração de Mensagem" onclick="forceGenerateAgentMessage(${a.id})">
                                <i class="bi bi-magic"></i>
                            </button>
                            <button class="btn btn-sm btn-light border" title="${a.status === 'active' ? 'Pausar' : 'Ativar'}" onclick="toggleAgentStatus(${a.id}, '${a.status === 'active' ? 'paused' : 'active'}')">
                                <i class="bi ${a.status === 'active' ? 'bi-pause-fill text-warning' : 'bi-play-fill text-success'}"></i>
                            </button>
                            <button class="btn btn-sm btn-light border text-danger" title="Excluir" onclick="deleteAgent(${a.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        listDiv.innerHTML = html;
    } catch (e) {
        listDiv.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger small"><i class="bi bi-exclamation-triangle me-2"></i>Erro ao carregar agentes.</td></tr>';
    }
}

async function createAgent() {
    const activeInstance = document.getElementById('instance-selector')?.value || activeInstanceName;
    if (!activeInstance) {
        alert("Selecione uma instância no topo (Feed) primeiro para poder enviar as mensagens do agente.");
        return;
    }

    const id = document.getElementById('agent-id').value;
    const name = document.getElementById('agent-name').value.trim();
    const recipient = document.getElementById('agent-recipient').value.trim();
    const prompt = document.getElementById('agent-prompt').value.trim();
    const interval = document.getElementById('agent-interval').value;
    const restricted = document.getElementById('agent-restricted').value.trim();
    const review = document.getElementById('agent-review').checked ? 1 : 0;

    const btnSave = document.getElementById('btn-save-agent');

    if (!name || !recipient || !prompt || !interval) {
        alert("Preencha todos os campos obrigatórios (*).");
        return;
    }

    btnSave.disabled = true;
    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Gravando...';

    try {
        const payload = {
            id: id,
            instance_name: activeInstance,
            name: name,
            prompt: prompt,
            recipient: recipient,
            interval_minutes: interval,
            restricted_hours: restricted,
            requires_review: review
        };

        const action = id ? 'edit_agent' : 'create_agent';
        const res = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (res.ok && data.success) {
            if (data.warning) {
                alert("Aviso: " + data.warning);
            }

            // Close modal
            const modalEl = document.getElementById('newAgentModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }

            // Reset form
            document.getElementById('new-agent-form').reset();
            document.getElementById('agent-id').value = '';

            // Reload list
            loadAgents();

            if (data.message && action === 'create_agent') {
                // Show a brief success message (optional, could use a toast)
            }
        } else {
            alert("Erro ao " + (id ? "editar" : "criar") + " agente: " + (data.error || "Falha desconhecida."));
            btnSave.disabled = false;
            btnSave.innerHTML = id ? '<i class="bi bi-check2-circle me-1"></i>Salvar Alterações' : '<i class="bi bi-check2-circle me-1"></i>Criar Agente';
        }
    } catch (e) {
        alert("Erro de comunicação ao gravar agente.");
        console.error(e);
        btnSave.disabled = false;
        btnSave.innerHTML = id ? '<i class="bi bi-check2-circle me-1"></i>Salvar Alterações' : '<i class="bi bi-check2-circle me-1"></i>Criar Agente';
    }
}

function openEditAgentModal(id) {
    const agent = loadedAgents.find(a => a.id == id);
    if (!agent) return;

    document.getElementById('agent-id').value = agent.id;
    document.getElementById('agent-name').value = agent.name;
    document.getElementById('agent-recipient').value = agent.recipient;
    document.getElementById('agent-prompt').value = agent.prompt;
    document.getElementById('agent-interval').value = agent.interval_minutes;
    document.getElementById('agent-restricted').value = agent.restricted_hours;
    document.getElementById('agent-review').checked = agent.requires_review == 1;

    document.getElementById('agent-modal-title').textContent = 'Editar Agente';
    document.getElementById('agent-btn-text').textContent = 'Salvar Alterações';

    const modal = new bootstrap.Modal(document.getElementById('newAgentModal'));
    modal.show();
}

async function forceGenerateAgentMessage(id) {
    if (!confirm("Isso fará a IA gerar uma nova mensagem para este agente imediatamente. A mensagem será enviada ou colocada para revisão a depender da configuração do agente. Continuar?")) return;

    try {
        const res = await fetch('api.php?action=force_generate_agent_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const data = await res.json();
        if (res.ok && data.success) {
            alert("Sucesso! Nova mensagem gerada e programada. Verifique na lista de Agendamentos.");
            loadSchedules(); // attempt to reload schedules if we're on a mixed view
        } else {
            alert("Erro ao gerar nova mensagem: " + (data.error || "Desconhecido"));
        }
    } catch (e) {
        alert("Erro de comunicação.");
    }
}

async function toggleAgentStatus(id, newStatus) {
    if (!confirm(`Deseja realmente alterar o status para ${newStatus}?`)) return;

    try {
        const res = await fetch('api.php?action=update_agent_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: newStatus })
        });

        if (res.ok) {
            loadAgents();
        } else {
            alert("Erro ao atualizar status.");
        }
    } catch (e) {
        alert("Erro de comunicação.");
    }
}

async function deleteAgent(id) {
    if (!confirm("Tem certeza que deseja EXCLUIR este agente? As mensagens já agendadas não serão canceladas automaticamente.")) return;

    try {
        const res = await fetch('api.php?action=delete_agent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        if (res.ok) {
            loadAgents();
        } else {
            alert("Erro ao deletar agente.");
        }
    } catch (e) {
        alert("Erro de comunicação.");
    }
}

async function approveSchedule(id) {
    if (!confirm("Aprovar e liberar esta mensagem para envio na hora marcada?")) return;
    try {
        const res = await fetch('api.php?action=update_schedule_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: 'pending' })
        });
        if (res.ok) {
            loadSchedules();
        } else {
            alert("Erro ao aprovar.");
        }
    } catch (e) {
        alert("Erro na requisição.");
    }
}
