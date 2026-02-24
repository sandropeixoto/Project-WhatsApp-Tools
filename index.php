<?php
// index.php

require_once __DIR__ . '/db.php';

// --- CONFIGURAÇÕES DA API ---
$API_BASE_URL = "https://sspeixoto.uazapi.com";
$ADMIN_TOKEN = "4cFCOnaDoBvSuhytYRRT5RaTRNSxP0ornjJDv9TdLvxmmaHDFO";

// --- ROTAS INTERNAS DO PAINEL ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true);

    // 1. Sincronizar Instâncias via API (Automático)
    if ($action === 'sync_instances') {
        $ch = curl_init("{$API_BASE_URL}/instance/all");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'admintoken: ' . $ADMIN_TOKEN
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $instances = json_decode($response, true);
            if (is_array($instances)) {
                $stmt = $pdo->prepare("INSERT INTO uazapi_instances (name, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token)");
                foreach ($instances as $inst) {
                    if (isset($inst['name']) && isset($inst['token'])) {
                        $stmt->execute([$inst['name'], $inst['token']]);
                    }
                }
                echo json_encode(['success' => true, 'count' => count($instances)]);
                exit;
            }
        }
        http_response_code($httpCode ?: 500);
        echo json_encode(['error' => 'Falha ao sincronizar com a API', 'details' => $response]);
        exit;
    }

    // 2. Buscar Instâncias do Banco Local para o Dropdown
    if ($action === 'get_instances') {
        $stmt = $pdo->query("SELECT name FROM uazapi_instances ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 3. Buscar Logs de uma Instância Específica
    if ($action === 'get_logs') {
        $name = $_GET['name'] ?? '';
        $stmt = $pdo->prepare("SELECT payload FROM uazapi_logs WHERE instance_name = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$name]);
        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $logs[] = json_decode($row['payload'], true);
        }
        echo json_encode($logs);
        exit;
    }

    // 4. Disparar Tools (Mensagem ou Status)
    if ($action === 'send_message' || $action === 'send_status') {
        $instanceName = $input['instance_name'] ?? '';

        // Pega o token correto da instância no banco de dados
        $stmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmt->execute([$instanceName]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(400);
            echo json_encode(['error' => 'Token da instância não encontrado no banco.']);
            exit;
        }

        $url = ($action === 'send_message') ? "{$API_BASE_URL}/send/text" : "{$API_BASE_URL}/send/status";

        $payload = [];
        if ($action === 'send_message') {
            $payload = ["number" => $input['number'] ?? '', "text" => $input['text'] ?? ''];
        }
        else {
            $payload = ["type" => $input['type']];
            if ($input['type'] === 'text') {
                $payload['text'] = $input['text'] ?? '';
                $payload['background_color'] = (int)($input['bg_color'] ?? 19);
                $payload['font'] = (int)($input['font'] ?? 1);
            }
            else {
                $payload['file'] = $input['file'] ?? '';
                if (!empty($input['text']))
                    $payload['text'] = $input['text'];
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instance['token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        http_response_code($httpCode);
        echo $response;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Multi-Instâncias uazapiGO</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #d1d7db;
            margin: 0;
            display: flex;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
        }

        .app-container {
            width: 100%;
            max-width: 1000px;
            background: #efeae2;
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .header {
            background-color: #f0f2f5;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #d1d7db;
            z-index: 10;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .header-info h1 {
            margin: 0;
            font-size: 16px;
            color: #111b21;
        }

        .instance-selector {
            margin-top: 5px;
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 13px;
            background: white;
            outline: none;
            min-width: 200px;
        }

        .tools-btn {
            background-color: #00a884;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        .chat-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .msg-row {
            display: flex;
            margin-bottom: 12px;
            width: 100%;
        }

        .msg-row.in {
            justify-content: flex-start;
        }

        .msg-row.out {
            justify-content: flex-end;
        }

        .bubble {
            max-width: 65%;
            padding: 6px 7px 8px 9px;
            border-radius: 7.5px;
            font-size: 14px;
            box-shadow: 0 1px 0.5px rgba(11, 20, 26, .13);
            display: flex;
            flex-direction: column;
        }

        .bubble.in {
            background-color: #ffffff;
            border-top-left-radius: 0;
        }

        .bubble.out {
            background-color: #d9fdd3;
            border-top-right-radius: 0;
        }

        .group-header-block {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 6px;
            padding: 6px;
            margin-bottom: 6px;
        }

        .info-line {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .tiny-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 8px;
            object-fit: cover;
        }

        .group-name {
            font-size: 12px;
            font-weight: 600;
            color: #53bdeb;
        }

        .sender-name {
            font-size: 12px;
            font-weight: 600;
            color: #128C7E;
        }

        .sender-phone {
            font-size: 11px;
            color: #667781;
            margin-left: 4px;
        }

        .msg-text {
            color: #111b21;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .msg-image {
            max-width: 100%;
            border-radius: 6px;
            margin-bottom: 4px;
        }

        .time {
            font-size: 11px;
            color: #667781;
            align-self: flex-end;
            margin-top: -10px;
            margin-left: 15px;
            float: right;
        }

        .sidebar {
            position: absolute;
            top: 0;
            right: -350px;
            width: 340px;
            height: 100%;
            background: #f0f2f5;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 20;
            display: flex;
            flex-direction: column;
        }

        .sidebar.open {
            right: 0;
        }

        .sidebar-header {
            background: #00a884;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 16px;
        }

        .close-btn {
            cursor: pointer;
            font-size: 24px;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .tool-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .tool-box h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .tool-box label {
            font-size: 12px;
            color: #667781;
            margin-bottom: 4px;
            display: block;
            font-weight: 600;
        }

        .tool-box input,
        .tool-box textarea,
        .tool-box select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 10px;
            box-sizing: border-box;
            font-size: 13px;
        }

        .btn-action {
            width: 100%;
            background: #00a884;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-sync {
            background: #34B7F1;
            margin-bottom: 15px;
        }

        .btn-sync:hover {
            background: #229dd1;
        }

        .status-msg {
            font-size: 12px;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="app-container">

        <div class="header">
            <div class="header-left">
                <img src="https://ui-avatars.com/api/?name=API&background=00a884&color=fff" alt="Perfil">
                <div class="header-info">
                    <h1>Central de Monitoramento</h1>
                    <select id="instance-selector" onchange="changeInstance()">
                        <option value="">Carregando instâncias...</option>
                    </select>
                </div>
            </div>
            <button class="tools-btn" onclick="toggleSidebar()">🛠 Ferramentas</button>
        </div>

        <div class="chat-container" id="monitor">
            <div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Selecione uma instância para
                visualizar</div>
        </div>

        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Painel de Controle</h2>
                <div class="close-btn" onclick="toggleSidebar()">&times;</div>
            </div>

            <div class="sidebar-content">

                <button class="btn-action btn-sync" onclick="syncApiInstances()" id="btn-sync">
                    🔄 Sincronizar Instâncias da API
                </button>
                <div id="sync-status" class="status-msg" style="margin-top: -5px; margin-bottom: 15px;"></div>

                <div class="tool-box">
                    <h3>💬 Enviar Mensagem (Instância Ativa)</h3>
                    <label>Número Destino</label>
                    <input type="text" id="send-number" placeholder="Ex: 5511999999999">
                    <label>Mensagem</label>
                    <textarea id="send-text" style="height: 60px;" placeholder="Mensagem..."></textarea>
                    <button class="btn-action" onclick="sendMessage()" id="btn-send">Enviar</button>
                    <div id="send-status" class="status-msg"></div>
                </div>

            </div>
        </div>

    </div>

    <script>
        let lastLogsString = '';

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
        function escapeHTML(str) { if (!str) return ''; return str.replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag])); }
        function formatTime(timestamp) { return new Date(timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); }

        // --- SINCRONIZAÇÃO AUTOMÁTICA ---
        async function syncApiInstances() {
            const btn = document.getElementById('btn-sync');
            const statusDiv = document.getElementById('sync-status');

            btn.disabled = true;
            btn.innerText = "Sincronizando...";
            statusDiv.innerHTML = "";

            try {
                const res = await fetch('index.php?action=sync_instances');
                const data = await res.json();

                if (res.ok) {
                    statusDiv.innerHTML = `<span style="color: green;">✔ ${data.count} instâncias atualizadas!</span>`;
                    loadInstances(); // Recarrega o dropdown
                } else {
                    statusDiv.innerHTML = `<span style="color: red;">Erro ao sincronizar.</span>`;
                }
            } catch (error) {
                statusDiv.innerHTML = `<span style="color: red;">Falha de comunicação.</span>`;
            } finally {
                btn.disabled = false;
                btn.innerText = "🔄 Sincronizar Instâncias da API";
            }
        }

        // --- GERENCIAMENTO DO DROPDOWN ---
        async function loadInstances() {
            const res = await fetch('index.php?action=get_instances');
            const instances = await res.json();
            const selector = document.getElementById('instance-selector');

            const currentSelected = selector.value;
            selector.innerHTML = '<option value="">-- Selecione uma instância --</option>';

            if (instances.length === 0) {
                selector.innerHTML = '<option value="">Nenhuma instância (Sincronize primeiro)</option>';
                return;
            }

            instances.forEach(inst => {
                const opt = document.createElement('option');
                opt.value = inst.name;
                opt.textContent = inst.name;
                selector.appendChild(opt);
            });

            // Mantém a seleção anterior se ela ainda existir
            if (currentSelected && instances.find(i => i.name === currentSelected)) {
                selector.value = currentSelected;
            } else if (instances.length > 0) {
                selector.value = instances[0].name;
                changeInstance();
            }
        }

        function changeInstance() {
            document.getElementById('monitor').innerHTML = '<div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Carregando mensagens da instância...</div>';
            lastLogsString = '';
            fetchLogs();
        }

        // --- DISPARO DE FERRAMENTAS ---
        async function sendMessage() {
            const activeInstance = document.getElementById('instance-selector').value;
            if (!activeInstance) return alert("Selecione uma instância no topo primeiro.");

            const number = document.getElementById('send-number').value.trim();
            const text = document.getElementById('send-text').value.trim();
            const statusDiv = document.getElementById('send-status');

            statusDiv.innerHTML = 'Enviando...';
            const res = await fetch('index.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ instance_name: activeInstance, number, text })
            });

            if (res.ok) {
                statusDiv.innerHTML = '<span style="color: green;">✔ Enviado!</span>';
                document.getElementById('send-text').value = '';
            } else {
                statusDiv.innerHTML = '<span style="color: red;">Erro no envio.</span>';
            }
        }

        // --- RENDERIZAÇÃO DA CONVERSA ---
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
                    if (msg.messageType === 'ImageMessage' && msg.content && msg.content.JPEGThumbnail) {
                        mediaHtml = `<img src="data:image/jpeg;base64,${msg.content.JPEGThumbnail}" class="msg-image" alt="Imagem">`;
                    }

                    let headerHtml = '';
                    if (msg.isGroup) {
                        const groupImage = chatInfo.imagePreview || 'https://ui-avatars.com/api/?name=G&background=dfe5e7&color=667781';
                        const groupName = chatInfo.name || msg.groupName || "Grupo";
                        let senderName = isFromMe ? "Você" : (msg.senderName || "Desconhecido");
                        let senderPhone = isFromMe ? activeInstance : (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');

                        headerHtml = `
                            <div class="group-header-block">
                                <div class="info-line">
                                    <img src="${groupImage}" class="tiny-avatar" alt="G">
                                    <span class="group-name">${escapeHTML(groupName)}</span>
                                </div>
                                <div class="info-line">
                                    <span class="sender-name">${escapeHTML(senderName)} <span class="sender-phone">(${senderPhone})</span></span>
                                </div>
                            </div>
                        `;
                    } else if (!isFromMe) {
                        const contactName = msg.senderName || chatInfo.name || "Contato";
                        const contactPhone = chatInfo.phone || (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');
                        headerHtml = `<div style="margin-bottom: 5px;"><span class="sender-name">${escapeHTML(contactName)}</span> ${contactPhone ? `<span class="sender-phone">(${contactPhone})</span>` : ''}</div>`;
                    }

                    let html = `
                        <div class="msg-row ${alignClass}">
                            <div class="bubble ${alignClass}">
                                ${headerHtml}
                                ${mediaHtml}
                                <div class="msg-text">${escapeHTML(msg.text || '')}</div>
                                <div class="time" style="margin-top: 5px;">${time} ${isFromMe ? '✓' : ''}</div>
                            </div>
                        </div>
                    `;
                    monitorDiv.innerHTML += html;
                });

                monitorDiv.scrollTop = monitorDiv.scrollHeight;

            } catch (error) { console.error("Erro:", error); }
        }

        // Inicia carregando as instâncias que já estão no banco
        loadInstances();
        setInterval(fetchLogs, 2000);
    </script>
</body>

</html>