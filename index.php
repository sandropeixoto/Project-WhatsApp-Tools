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

    // 1. Sincronizar Instâncias via API
    if ($action === 'sync_instances') {
        $ch = curl_init("{$API_BASE_URL}/instance/all");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['admintoken: ' . $ADMIN_TOKEN]);

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
        $stmt = $pdo->prepare("SELECT payload FROM (SELECT id, payload FROM uazapi_logs WHERE instance_name = ? ORDER BY id DESC LIMIT 50) AS recent ORDER BY id ASC");
        $stmt->execute([$name]);
        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $logs[] = json_decode($row['payload'], true);
        }
        echo json_encode($logs);
        exit;
    }

    // 4. Enviar Mensagem
    if ($action === 'send_message') {
        $instanceName = $input['instance_name'] ?? '';
        $stmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmt->execute([$instanceName]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(400);
            echo json_encode(['error' => 'Token da instância não encontrado.']);
            exit;
        }

        $payload = ["number" => $input['number'] ?? '', "text" => $input['text'] ?? ''];

        $ch = curl_init("{$API_BASE_URL}/send/text");
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

    // 5. Enviar Status/Stories
    if ($action === 'send_status') {
        $instanceName = $input['instance_name'] ?? '';
        $stmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmt->execute([$instanceName]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(400);
            echo json_encode(['error' => 'Token da instância não encontrado.']);
            exit;
        }

        $payload = ['type' => $input['type'] ?? 'text'];
        if ($input['type'] === 'text') {
            $payload['text'] = $input['text'] ?? '';
            $payload['background_color'] = (int)($input['bg_color'] ?? 19);
            $payload['font'] = (int)($input['font'] ?? 1);
        }
        else {
            $payload['file'] = $input['file'] ?? '';
            if (!empty($input['text'])) {
                $payload['text'] = $input['text'];
            }
        }

        $ch = curl_init("{$API_BASE_URL}/send/status");
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

    // 6. Sincronizar Grupos via API
    if ($action === 'sync_groups') {
        $instanceName = $input['instance_name'] ?? '';
        $stmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmt->execute([$instanceName]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(400);
            echo json_encode(['error' => 'Token da instância não encontrado.']);
            exit;
        }

        $ch = curl_init("{$API_BASE_URL}/group/list?force=true&noparticipants=false");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instance['token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $groups = $data['groups'] ?? $data ?? [];

            if (is_array($groups)) {
                $stmt = $pdo->prepare("INSERT INTO uazapi_groups 
                    (jid, instance_name, name, description, owner_jid, participant_count, is_announce, is_locked, is_admin, invite_link, group_created) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        name = VALUES(name),
                        description = VALUES(description),
                        owner_jid = VALUES(owner_jid),
                        participant_count = VALUES(participant_count),
                        is_announce = VALUES(is_announce),
                        is_locked = VALUES(is_locked),
                        is_admin = VALUES(is_admin),
                        invite_link = VALUES(invite_link),
                        group_created = VALUES(group_created)");

                foreach ($groups as $g) {
                    $jid = $g['JID'] ?? '';
                    if (empty($jid))
                        continue;

                    $participantCount = isset($g['Participants']) && is_array($g['Participants']) ? count($g['Participants']) : 0;
                    $groupCreated = !empty($g['GroupCreated']) ? date('Y-m-d H:i:s', strtotime($g['GroupCreated'])) : null;

                    $stmt->execute([
                        $jid,
                        $instanceName,
                        $g['Name'] ?? null,
                        $g['Topic'] ?? null,
                        $g['OwnerJID'] ?? null,
                        $participantCount,
                        !empty($g['IsAnnounce']) ? 1 : 0,
                        !empty($g['IsLocked']) ? 1 : 0,
                        !empty($g['OwnerIsAdmin']) ? 1 : 0,
                        $g['invite_link'] ?? null,
                        $groupCreated
                    ]);
                }
                echo json_encode(['success' => true, 'count' => count($groups)]);
                exit;
            }
        }
        http_response_code($httpCode ?: 500);
        echo json_encode(['error' => 'Falha ao buscar grupos', 'details' => $response]);
        exit;
    }

    // 7. Buscar Grupos do Banco
    if ($action === 'get_groups') {
        $name = $_GET['name'] ?? '';
        $stmt = $pdo->prepare("SELECT jid, name, description, owner_jid, participant_count, is_announce, is_locked, is_admin, invite_link, updated_at FROM uazapi_groups WHERE instance_name = ? ORDER BY name ASC");
        $stmt->execute([$name]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 8. Salvar mídia localmente no servidor
    if ($action === 'save_media') {
        $fileUrl = $input['file_url'] ?? '';
        $fileName = $input['file_name'] ?? '';
        $msgType = $input['msg_type'] ?? '';

        if (empty($fileUrl)) {
            http_response_code(400);
            echo json_encode(['error' => 'URL do arquivo não informada.']);
            exit;
        }

        $mediaDir = __DIR__ . '/media';
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0777, true);
        }

        // Determinar extensão pelo tipo ou URL
        $ext = '';
        if (!empty($fileName)) {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        }
        if (empty($ext)) {
            $urlPath = parse_url($fileUrl, PHP_URL_PATH);
            $ext = pathinfo($urlPath, PATHINFO_EXTENSION);
        }
        if (empty($ext)) {
            $extMap = [
                'ImageMessage' => 'jpg', 'StickerMessage' => 'webp',
                'VideoMessage' => 'mp4', 'GifMessage' => 'gif',
                'AudioMessage' => 'mp3', 'PTTMessage' => 'ogg',
                'DocumentMessage' => 'bin'
            ];
            $ext = $extMap[$msgType] ?? 'bin';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName ?: 'media', PATHINFO_FILENAME));
        $localFileName = date('Ymd_His') . '_' . $safeName . '.' . $ext;
        $localPath = $mediaDir . '/' . $localFileName;

        // Baixar o arquivo
        $ch = curl_init($fileUrl);
        $fp = fopen($localPath, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $success = curl_exec($ch);
        $dlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($success && $dlCode === 200 && filesize($localPath) > 0) {
            $publicUrl = 'media/' . $localFileName;
            echo json_encode(['success' => true, 'local_url' => $publicUrl, 'file_name' => $localFileName]);
        }
        else {
            @unlink($localPath);
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao baixar o arquivo.', 'http_code' => $dlCode]);
        }
        exit;
    }

    // 9. Download de mídia sob demanda via API (para stickers/áudio sem fileURL)
    if ($action === 'download_media_api') {
        $instanceName = $input['instance_name'] ?? '';
        $messageId = $input['message_id'] ?? '';

        if (empty($instanceName) || empty($messageId)) {
            http_response_code(400);
            echo json_encode(['error' => 'instance_name e message_id são obrigatórios.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmt->execute([$instanceName]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(400);
            echo json_encode(['error' => 'Token da instância não encontrado.']);
            exit;
        }

        $dlPayload = json_encode([
            'id' => $messageId,
            'return_link' => true,
            'return_base64' => false
        ]);

        $ch = curl_init("{$API_BASE_URL}/message/download");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dlPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instance['token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

        .header-right {
            display: flex;
            gap: 8px;
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

        .groups-btn {
            background-color: #34B7F1;
        }

        .groups-btn:hover {
            background-color: #229dd1;
        }

        /* --- TABS: Chat vs Grupos --- */
        .tab-bar {
            display: flex;
            background: #f0f2f5;
            border-bottom: 1px solid #d1d7db;
        }

        .tab-bar button {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #667781;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab-bar button.active {
            color: #00a884;
            border-bottom-color: #00a884;
        }

        .tab-content {
            display: none;
            flex: 1;
            overflow-y: auto;
        }

        .tab-content.active {
            display: flex;
            flex-direction: column;
        }

        /* --- Chat --- */
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
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .msg-image:hover {
            opacity: 0.9;
        }

        .msg-sticker {
            max-width: 150px;
            max-height: 150px;
            margin-bottom: 4px;
        }

        .msg-video {
            max-width: 100%;
            border-radius: 6px;
            margin-bottom: 4px;
            background: #000;
        }

        .msg-audio {
            width: 100%;
            margin-bottom: 4px;
            height: 36px;
        }

        .msg-document {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 4px;
            text-decoration: none;
            color: #111b21;
            transition: background 0.2s;
        }

        .msg-document:hover {
            background: rgba(0, 0, 0, 0.08);
        }

        .doc-icon {
            font-size: 28px;
            flex-shrink: 0;
        }

        .doc-info {
            flex: 1;
            min-width: 0;
        }

        .doc-name {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-meta {
            font-size: 11px;
            color: #667781;
        }

        .media-placeholder {
            background: rgba(0, 0, 0, 0.06);
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            font-size: 12px;
            color: #667781;
            margin-bottom: 4px;
        }

        .media-placeholder span {
            font-size: 20px;
            display: block;
            margin-bottom: 4px;
        }

        .media-wrapper {
            position: relative;
            display: inline-block;
            max-width: 100%;
        }

        .btn-save-media {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(0, 0, 0, 0.55);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 2;
        }

        .media-wrapper:hover .btn-save-media {
            opacity: 1;
        }

        .btn-save-media:hover {
            background: rgba(0, 168, 132, 0.85);
        }

        .btn-save-media.saved {
            opacity: 1;
            background: rgba(0, 168, 132, 0.85);
            cursor: default;
        }

        .btn-save-inline {
            background: rgba(0, 0, 0, 0.06);
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            color: #00a884;
            font-weight: 600;
            margin-left: 8px;
            transition: background 0.2s;
        }

        .btn-save-inline:hover {
            background: rgba(0, 168, 132, 0.15);
        }

        .btn-save-inline.saved {
            color: #16a34a;
            cursor: default;
        }

        .time {
            font-size: 11px;
            color: #667781;
            align-self: flex-end;
            margin-top: -10px;
            margin-left: 15px;
            float: right;
        }

        /* --- Grupos --- */
        .groups-container {
            padding: 15px;
        }

        .groups-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .groups-header h3 {
            margin: 0;
            font-size: 14px;
            color: #111b21;
        }

        .btn-sync-groups {
            background: #00a884;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 16px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-sync-groups:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .groups-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .groups-table th {
            background: #f0f2f5;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            color: #667781;
            border-bottom: 1px solid #e0e0e0;
        }

        .groups-table td {
            padding: 8px;
            border-bottom: 1px solid #f0f2f5;
            color: #111b21;
            vertical-align: top;
        }

        .groups-table tr:hover {
            background: #f7f8fa;
        }

        .jid-cell {
            font-family: monospace;
            font-size: 11px;
            color: #00a884;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .jid-cell:hover {
            text-decoration: underline;
        }

        .copy-icon {
            font-size: 10px;
            opacity: 0.5;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-yes {
            background: #dcfce7;
            color: #16a34a;
        }

        .badge-no {
            background: #fee2e2;
            color: #dc2626;
        }

        /* --- Sidebar --- */
        .sidebar {
            position: absolute;
            top: 0;
            right: -380px;
            width: 370px;
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

        .hidden {
            display: none !important;
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
            <div class="header-right">
                <button class="tools-btn" onclick="toggleSidebar()">🛠 Ferramentas</button>
            </div>
        </div>

        <!-- Abas -->
        <div class="tab-bar">
            <button class="active" onclick="switchTab('chat', this)">💬 Conversas</button>
            <button onclick="switchTab('groups', this)">👥 Grupos</button>
        </div>

        <!-- Tab: Conversas -->
        <div class="tab-content active" id="tab-chat">
            <div class="chat-container" id="monitor">
                <div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Selecione uma instância
                    para visualizar</div>
            </div>
        </div>

        <!-- Tab: Grupos -->
        <div class="tab-content" id="tab-groups">
            <div class="groups-container">
                <div class="groups-header">
                    <h3>Grupos da Instância</h3>
                    <button class="btn-sync-groups" id="btn-sync-groups" onclick="syncGroups()">🔄 Atualizar
                        Grupos</button>
                </div>
                <div id="sync-groups-status" class="status-msg" style="margin-bottom: 10px;"></div>
                <div id="groups-list">
                    <div style="text-align: center; color: #666; font-size: 13px; padding: 30px 0;">Selecione uma
                        instância e clique em "Atualizar Grupos"</div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
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

                <!-- Enviar Mensagem -->
                <div class="tool-box">
                    <h3>💬 Enviar Mensagem</h3>
                    <label>Número Destino</label>
                    <input type="text" id="send-number" placeholder="Ex: 5511999999999">
                    <label>Mensagem</label>
                    <textarea id="send-text" style="height: 60px;" placeholder="Mensagem..."></textarea>
                    <button class="btn-action" onclick="sendMessage()" id="btn-send">Enviar</button>
                    <div id="send-status" class="status-msg"></div>
                </div>

                <!-- Enviar Status/Stories -->
                <div class="tool-box">
                    <h3>📸 Enviar Status / Stories</h3>
                    <label>Tipo</label>
                    <select id="status-type" onchange="toggleStatusFields()">
                        <option value="text">Texto</option>
                        <option value="image">Imagem</option>
                        <option value="video">Vídeo</option>
                        <option value="audio">Áudio</option>
                    </select>

                    <div id="status-text-fields">
                        <label>Cor de Fundo</label>
                        <select id="status-bg-color">
                            <option value="1">Amarelo 1</option>
                            <option value="2">Amarelo 2</option>
                            <option value="3">Amarelo 3</option>
                            <option value="4">Verde 1</option>
                            <option value="5">Verde 2</option>
                            <option value="6">Verde 3</option>
                            <option value="7">Azul 1</option>
                            <option value="8">Azul 2</option>
                            <option value="9">Azul 3</option>
                            <option value="10">Lilás 1</option>
                            <option value="11">Lilás 2</option>
                            <option value="12">Lilás 3</option>
                            <option value="13">Magenta</option>
                            <option value="14">Rosa 1</option>
                            <option value="15">Rosa 2</option>
                            <option value="16">Marrom</option>
                            <option value="17">Cinza 1</option>
                            <option value="18">Cinza 2</option>
                            <option value="19" selected>Cinza 3 (padrão)</option>
                        </select>
                        <label>Fonte</label>
                        <select id="status-font">
                            <option value="0">Padrão</option>
                            <option value="1" selected>Estilo 1</option>
                            <option value="2">Estilo 2</option>
                            <option value="3">Estilo 3</option>
                            <option value="4">Estilo 4</option>
                            <option value="5">Estilo 5</option>
                            <option value="6">Estilo 6</option>
                            <option value="7">Estilo 7</option>
                            <option value="8">Estilo 8</option>
                        </select>
                    </div>

                    <div id="status-media-fields" class="hidden">
                        <label>URL do arquivo</label>
                        <input type="text" id="status-file" placeholder="https://exemplo.com/imagem.jpg">
                    </div>

                    <label>Texto / Legenda</label>
                    <textarea id="status-text" style="height: 60px;" placeholder="Texto do status..."></textarea>
                    <button class="btn-action" onclick="sendStatus()">Publicar Status</button>
                    <div id="status-send-status" class="status-msg"></div>
                </div>

            </div>
        </div>

    </div>

    <script>
        let lastLogsString = '';

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
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
            document.querySelectorAll('.tab-bar button').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            btn.classList.add('active');

            if (tabId === 'groups') loadGroups();
        }

        // --- TOGGLE STATUS FIELDS ---
        function toggleStatusFields() {
            const type = document.getElementById('status-type').value;
            document.getElementById('status-text-fields').classList.toggle('hidden', type !== 'text');
            document.getElementById('status-media-fields').classList.toggle('hidden', type === 'text');
        }

        // --- SINCRONIZAÇÃO DE INSTÂNCIAS ---
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
                    loadInstances();
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

        // --- ENVIAR MENSAGEM ---
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

        // --- ENVIAR STATUS/STORIES ---
        async function sendStatus() {
            const activeInstance = document.getElementById('instance-selector').value;
            if (!activeInstance) return alert("Selecione uma instância no topo primeiro.");

            const type = document.getElementById('status-type').value;
            const text = document.getElementById('status-text').value.trim();
            const statusDiv = document.getElementById('status-send-status');

            const payload = { instance_name: activeInstance, type, text };

            if (type === 'text') {
                payload.bg_color = document.getElementById('status-bg-color').value;
                payload.font = document.getElementById('status-font').value;
            } else {
                payload.file = document.getElementById('status-file').value.trim();
                if (!payload.file) return alert("Insira a URL do arquivo de mídia.");
            }

            if (type === 'text' && !text) return alert("Insira o texto do status.");

            statusDiv.innerHTML = 'Publicando...';
            try {
                const res = await fetch('index.php?action=send_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    statusDiv.innerHTML = '<span style="color: green;">✔ Status publicado!</span>';
                    document.getElementById('status-text').value = '';
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
                const res = await fetch('index.php?action=sync_groups', {
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
                const res = await fetch(`index.php?action=get_groups&name=${activeInstance}`);
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
                        <td style="text-align:center;"><span class="badge ${g.is_admin == 1 ? 'badge-yes' : 'badge-no'}">${g.is_admin == 1 ? '✅ Sim' : '❌ Não'}</span></td>
                        <td style="text-align:center;"><span class="badge ${g.is_announce == 1 ? 'badge-yes' : 'badge-no'}">${g.is_announce == 1 ? 'Só Admins' : 'Todos'}</span></td>
                    </tr>`;
                });

                html += '</tbody></table>';
                listDiv.innerHTML = html;

            } catch (e) {
                listDiv.innerHTML = '<div style="text-align: center; color: red; font-size: 13px; padding: 30px 0;">Erro ao carregar grupos.</div>';
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
                const res = await fetch('index.php?action=save_media', {
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
                        cont contactName = msg.senderName || chatInfo.name || "Contato";
                        const contactPhone = chatInfo.phone || (msg.sender_pn ? msg.sender_pn.split('@')[0] : '');
                        headerHtml = `<div style="margin-bottom: 5px;"><span class="sender-name">${escapeHTML(contactName)}</span> ${contactPhon ? `<span class="sender-phone">(${contactPhone})</span>` : ''}</div>`;
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
                    ;

                    monitorDiv.scrollTop = monitorDiv.scrollHeight;

                } catch (error) { console.error("Erro:", error); }
            }

        // Inicia carregando as instâncias que já estão no banco
        loadInstances();
            setInterval(fetchLogs, 2000);
    </script>
</body>

</html>