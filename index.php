<?php
$pageTitle = 'Feed';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="d-flex flex-grow-1 overflow-hidden position-relative">
    <!-- Chat Sidebar -->
    <aside class="chat-sidebar" id="chat-sidebar">
        <div class="chat-sidebar-header">
            <div class="d-flex align-items-center gap-2">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($activeInstanceName); ?>&background=00a884&color=fff" class="rounded-circle" style="width: 40px; height: 40px;" alt="">
                <span class="fw-bold small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($activeInstanceName); ?></span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-link text-secondary p-1" title="Nova Conversa"><i class="bi bi-chat-left-text-fill fs-5"></i></button>
                <button class="btn btn-link text-secondary p-1" title="Mais opções"><i class="bi bi-three-dots-vertical fs-5"></i></button>
            </div>
        </div>

        <div class="search-container">
            <div class="search-box">
                <i class="bi bi-search text-secondary small"></i>
                <input type="text" placeholder="Pesquisar ou começar uma nova conversa" id="chat-search">
            </div>
        </div>

        <div class="chat-list" id="chat-list">
            <div class="text-center py-5">
                <div class="spinner-border text-success spinner-border-sm" role="status"></div>
                <div class="mt-2 text-muted small">Carregando conversas...</div>
            </div>
        </div>
    </aside>

    <!-- Chat Main Area -->
    <main class="chat-main">
        <!-- Empty State -->
        <div id="chat-empty-state" class="empty-chat-state h-100">
            <div class="mb-4">
                <i class="bi bi-whatsapp"></i>
            </div>
            <h3 class="fw-light text-dark">WhatsApp Web</h3>
            <p class="mb-0">Envie e receba mensagens sem precisar manter seu celular conectado.<br>Use o WhatsApp em até 4 dispositivos vinculados e 1 celular ao mesmo tempo.</p>
            <div class="mt-auto text-muted small">
                <i class="bi bi-lock-fill"></i> Mensagens protegidas com a criptografia de ponta a ponta
            </div>
        </div>

        <!-- Active Chat -->
        <div id="active-chat" class="d-none flex-column h-100">
            <header class="chat-header">
                <button class="btn btn-link d-md-none text-secondary p-0 me-2" onclick="toggleSidebar(true)">
                    <i class="bi bi-arrow-left fs-4"></i>
                </button>
                <img id="active-chat-avatar" src="" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" alt="">
                <div class="flex-grow-1 min-width-0">
                    <div id="active-chat-name" class="fw-bold text-dark text-truncate"></div>
                    <div id="active-chat-status" class="small text-muted">online</div>
                </div>
                <div class="d-flex gap-3 text-secondary">
                    <i class="bi bi-search cursor-pointer"></i>
                    <i class="bi bi-three-dots-vertical cursor-pointer"></i>
                </div>
            </header>

            <div id="monitor" class="chat-container chat-bg">
                <!-- Mensagens carregadas via JS -->
            </div>

            <!-- Input Area (Visual apenas por enquanto) -->
            <footer class="bg-light p-2 d-flex align-items-center gap-2 border-top">
                <button class="btn btn-link text-secondary"><i class="bi bi-emoji-smile fs-4"></i></button>
                <button class="btn btn-link text-secondary"><i class="bi bi-plus-lg fs-4"></i></button>
                <div class="flex-grow-1">
                    <input type="text" class="form-control border-0 py-2 shadow-none" placeholder="Digite uma mensagem" disabled>
                </div>
                <button class="btn btn-link text-secondary"><i class="bi bi-mic-fill fs-4"></i></button>
            </footer>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<script>
let currentJid = null;
const instanceName = "<?php echo $activeInstanceName; ?>";

async function loadChatList() {
    try {
        const response = await fetch(`api.php?action=get_chats&name=${encodeURIComponent(instanceName)}`);
        const chats = await response.json();
        renderChatList(chats);
    } catch (e) {
        console.error("Erro ao carregar lista de chats", e);
    }
}

function renderChatList(chats) {
    const list = document.getElementById('chat-list');
    if (chats.length === 0) {
        list.innerHTML = '<div class="text-center py-5 text-muted small">Nenhuma conversa encontrada.</div>';
        return;
    }

    list.innerHTML = chats.map(chat => {
        const time = chat.timestamp ? new Date(chat.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
        const lastMsg = chat.last_message_text || '';
        const avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(chat.name)}&background=random&color=fff`;
        
        return `
            <div class="chat-item ${currentJid === chat.jid ? 'active' : ''}" onclick="selectChat('${chat.jid}', '${chat.name}', '${avatar}')">
                <img src="${avatar}" class="chat-item-avatar" alt="">
                <div class="chat-item-info">
                    <div class="chat-item-top">
                        <span class="chat-item-name">${chat.name}</span>
                        <span class="chat-item-time">${time}</span>
                    </div>
                    <div class="chat-item-msg">${lastMsg}</div>
                </div>
            </div>
        `;
    }).join('');
}

async function selectChat(jid, name, avatar) {
    currentJid = jid;
    
    // UI Updates
    document.getElementById('chat-empty-state').classList.add('d-none');
    document.getElementById('active-chat').classList.remove('d-none');
    document.getElementById('active-chat-name').textContent = name;
    document.getElementById('active-chat-avatar').src = avatar;
    
    // Highlight in list
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    // Find the clicked item if triggered by click
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    if (window.innerWidth < 768) toggleSidebar(false);

    await loadMessages(jid);
}

async function loadMessages(jid) {
    const monitor = document.getElementById('monitor');
    monitor.innerHTML = '<div class="text-center py-5 text-muted small">Carregando mensagens do banco...</div>';
    
    try {
        const response = await fetch(`api.php?action=get_chat_messages&name=${encodeURIComponent(instanceName)}&jid=${encodeURIComponent(jid)}`);
        const messages = await response.json();
        
        if (messages.length === 0) {
            monitor.innerHTML = '<div class="text-center py-5 text-muted small">Nenhuma mensagem registrada no banco para esta conversa.</div>';
            return;
        }

        monitor.innerHTML = messages.map(msg => {
            const isOut = msg.from_me == 1;
            const timestamp = msg.message_timestamp ? parseInt(msg.message_timestamp) : (new Date(msg.created_at).getTime() / 1000);
            const time = new Date(timestamp * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            let content = '';
            if (msg.message_type === 'ImageMessage' && msg.file_url) {
                content = `<img src="${msg.file_url}" class="msg-image shadow-sm mb-2" onclick="window.open('${msg.file_url}')">`;
            } else if (msg.message_type === 'AudioMessage' && msg.file_url) {
                content = `<audio controls class="msg-audio"><source src="${msg.file_url}" type="${msg.mimetype || 'audio/ogg'}"></audio>`;
            } else if (msg.message_type === 'VideoMessage' && msg.file_url) {
                content = `<video controls class="msg-video shadow-sm mb-2"><source src="${msg.file_url}" type="${msg.mimetype || 'video/mp4'}"></video>`;
            }
            
            if (msg.text) {
                content += `<div class="msg-text">${msg.text}</div>`;
            }

            // Se for grupo, mostra o nome do remetente
            const senderInfo = (msg.is_group == 1 && !isOut) ? `<div class="sender-name small mb-1">${msg.sender_name || msg.sender_jid}</div>` : '';
            
            return `
                <div class="msg-row ${isOut ? 'out' : 'in'}">
                    <div class="bubble ${isOut ? 'out' : 'in'}">
                        ${senderInfo}
                        ${content}
                        <div class="time">${time}</div>
                    </div>
                </div>
            `;
        }).join('');
        
        monitor.scrollTop = monitor.scrollHeight;
    } catch (e) {
        console.error("Erro ao carregar mensagens", e);
        monitor.innerHTML = '<div class="text-center py-5 text-danger small">Erro ao carregar mensagens do banco de dados.</div>';
    }
}

function toggleSidebar(show) {
    const sidebar = document.getElementById('chat-sidebar');
    if (show) sidebar.classList.remove('hidden');
    else sidebar.classList.add('hidden');
}

// Initial load
loadChatList();

// Refresh list every 30s
setInterval(loadChatList, 30000);
</script>
