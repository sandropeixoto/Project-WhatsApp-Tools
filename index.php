<?php
// index.php

require_once __DIR__ . '/db.php';

$activeInstanceName = '';
if (!isset($_GET['action'])) {
    if (!isset($_GET['instance']) || empty(trim($_GET['instance']))) {
        header('Location: instances.php');
        exit;
    }
    $activeInstanceName = trim($_GET['instance']);
}

// --- CONFIGURAÇÕES DA API ---
$API_BASE_URL = "https://sspeixoto.uazapi.com";
$ADMIN_TOKEN = "4cFCOnaDoBvSuhytYRRT5RaTRNSxP0ornjJDv9TdLvxmmaHDFO";

// --- ROTAS INTERNAS DO PAINEL ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true);

    // 1. Sincronizar Instâncias via API (com dados enriquecidos)
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
                $stmt = $pdo->prepare("INSERT INTO uazapi_instances 
                    (name, token, status, profile_name, profile_pic_url, phone_number, is_business, platform) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        token = VALUES(token),
                        status = VALUES(status),
                        profile_name = VALUES(profile_name),
                        profile_pic_url = VALUES(profile_pic_url),
                        phone_number = VALUES(phone_number),
                        is_business = VALUES(is_business),
                        platform = VALUES(platform)");

                foreach ($instances as $inst) {
                    if (isset($inst['name']) && isset($inst['token'])) {
                        // Extrair telefone do owner ou do jid
                        $phone = '';
                        if (!empty($inst['owner'])) {
                            $phone = preg_replace('/[^0-9]/', '', explode('@', $inst['owner'])[0]);
                        }

                        $stmt->execute([
                            $inst['name'],
                            $inst['token'],
                            $inst['status'] ?? 'disconnected',
                            $inst['profileName'] ?? null,
                            $inst['profilePicUrl'] ?? null,
                            $phone ?: null,
                            !empty($inst['isBusiness']) ? 1 : 0,
                            $inst['plataform'] ?? null
                        ]);
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

    // 2. Buscar Instâncias do Banco Local
    if ($action === 'get_instances') {
        $stmt = $pdo->query("SELECT name, status, profile_name, profile_pic_url, phone_number, is_business, platform FROM uazapi_instances ORDER BY name ASC");
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

    // 4b. Agendar Mensagem de Texto
    if ($action === 'schedule_message') {
        $instanceName = $input['instance_name'] ?? '';
        $schedule = $input['schedule'] ?? 'now';
        $number = $input['number'] ?? '';
        $text = $input['text'] ?? '';

        if (empty($instanceName) || empty($number) || empty($text)) {
            http_response_code(400);
            echo json_encode(['error' => 'Preencha todos os campos.']);
            exit;
        }

        $apiPayload = ['number' => $number, 'text' => $text];

        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        switch ($schedule) {
            case '+5min':
                $now->modify('+5 minutes');
                break;
            case '+10min':
                $now->modify('+10 minutes');
                break;
            case '+30min':
                $now->modify('+30 minutes');
                break;
            case '+1hour':
                $now->modify('+1 hour');
                break;
            case '+2hours':
                $now->modify('+2 hours');
                break;
            case 'tomorrow_8':
                $now->modify('+1 day');
                $now->setTime(8, 0, 0);
                break;
            case 'tomorrow_same':
                $now->modify('+1 day');
                break;
            default:
                $now->modify('+5 minutes');
        }
        $scheduledAt = $now->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at) VALUES (?, 'message', ?, ?)");
        $stmt->execute([$instanceName, json_encode($apiPayload), $scheduledAt]);

        echo json_encode(['success' => true, 'scheduled_at' => $scheduledAt, 'id' => $pdo->lastInsertId()]);
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

    // 5b. Agendar Status
    if ($action === 'schedule_status') {
        $instanceName = $input['instance_name'] ?? '';
        $schedule = $input['schedule'] ?? 'now';

        if (empty($instanceName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Selecione uma instância.']);
            exit;
        }

        // Montar payload da API
        $apiPayload = ['type' => $input['type'] ?? 'text'];
        if ($input['type'] === 'text') {
            $apiPayload['text'] = $input['text'] ?? '';
            $apiPayload['background_color'] = (int)($input['bg_color'] ?? 19);
            $apiPayload['font'] = (int)($input['font'] ?? 1);
        }
        else {
            $apiPayload['file'] = $input['file'] ?? '';
            if (!empty($input['text'])) {
                $apiPayload['text'] = $input['text'];
            }
        }

        // Calcular horário agendado
        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        switch ($schedule) {
            case '+5min':
                $now->modify('+5 minutes');
                break;
            case '+10min':
                $now->modify('+10 minutes');
                break;
            case '+30min':
                $now->modify('+30 minutes');
                break;
            case '+1hour':
                $now->modify('+1 hour');
                break;
            case '+2hours':
                $now->modify('+2 hours');
                break;
            case 'tomorrow_8':
                $now->modify('+1 day');
                $now->setTime(8, 0, 0);
                break;
            case 'tomorrow_same':
                $now->modify('+1 day');
                break;
            default:
                $now->modify('+5 minutes');
        }
        $scheduledAt = $now->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at) VALUES (?, 'status', ?, ?)");
        $stmt->execute([$instanceName, json_encode($apiPayload), $scheduledAt]);

        echo json_encode(['success' => true, 'scheduled_at' => $scheduledAt, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // 5c. Listar Agendamentos
    if ($action === 'get_schedules') {
        $name = $_GET['name'] ?? '';
        $stmt = $pdo->prepare("SELECT id, task_type, payload, scheduled_at, status, created_at, executed_at FROM uazapi_schedule WHERE instance_name = ? ORDER BY scheduled_at DESC LIMIT 30");
        $stmt->execute([$name]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 5d. Cancelar Agendamento
    if ($action === 'cancel_schedule') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE uazapi_schedule SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
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
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Tools — Feed</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wa-primary: #00a884;
            --wa-dark: #075e54;
            --wa-teal: #128c7e;
            --wa-bg: #f0f2f5;
            --wa-chat-bg: #efeae2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--wa-bg);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar-wa {
            background: var(--wa-primary);
            color: white;
            z-index: 1040;
        }

        /* Chat Wrapper Background */
        .chat-bg {
            background-color: var(--wa-chat-bg);
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            background-repeat: repeat;
        }

        /* Keep existing chat bubble CSS */
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

        /* Groups styling compatibility */
        .groups-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .groups-table th {
            background: var(--wa-bg);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #667781;
            border-bottom: 1px solid #e0e0e0;
        }

        .groups-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f2f5;
            color: #111b21;
            vertical-align: middle;
        }

        .groups-table tr:hover {
            background: #f7f8fa;
        }

        .jid-cell {
            font-family: monospace;
            font-size: 12px;
            color: var(--wa-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .jid-cell:hover {
            text-decoration: underline;
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

        .custom-nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            padding: 12px 20px;
        }

        .custom-nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--wa-primary);
        }

        .custom-nav-tabs .nav-link.active {
            color: var(--wa-primary);
            border-color: var(--wa-primary);
            background: transparent;
        }
    </style>
</head>

<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-wa shadow-sm px-3 py-2 flex-shrink-0">
        <div class="d-flex align-items-center w-100 justify-content-between">
            <div class="d-flex align-items-center">
                <img id="topbar-avatar" src="https://ui-avatars.com/api/?name=WA&background=128c7e&color=fff"
                    class="rounded-circle border border-2 border-white border-opacity-50 me-3"
                    style="width: 44px; height: 44px; object-fit: cover;" alt="">
                <div>
                    <div class="fw-bold lh-1 mb-1 fs-6" id="topbar-name">Carregando...</div>
                    <div class="small text-white-50 lh-1" id="topbar-phone"></div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-light btn-sm fw-semibold px-3" onclick="switchInstance()">
                    <i class="bi bi-arrow-return-left me-1"></i> Trocar
                </button>
                <button class="btn btn-light btn-sm fw-semibold px-3 text-success shadow-sm" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                    <i class="bi bi-tools me-1"></i> Ferramentas
                </button>
            </div>
        </div>
    </nav>

    <!-- Hidden selector for backward compat -->
    <select id="instance-selector" class="d-none"></select>

    <!-- Main Content -->
    <main class="d-flex flex-column flex-grow-1 overflow-hidden" style="background: white;">

        <!-- Default Bootstrap Nav Tabs styled nicely -->
        <ul class="nav custom-nav-tabs tab-bar border-bottom w-100" style="background: var(--wa-bg);">
            <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100 active" onclick="switchTab('chat', this)">
                    <i class="bi bi-chat-dots me-2"></i> Conversas
                </button>
            </li>
            <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100" onclick="switchTab('groups', this)">
                    <i class="bi bi-people me-2"></i> Grupos
                </button>
            </li>
        </ul>

        <!-- Tab: Conversas -->
        <div class="tab-content active chat-bg" id="tab-chat">
            <div class="chat-container w-100 mx-auto" id="monitor" style="max-width: 900px;">
                <div class="text-center text-muted my-4 small">Carregando mensagens...</div>
            </div>
        </div>

        <!-- Tab: Grupos -->
        <div class="tab-content chat-bg" id="tab-groups">
            <div class="container-fluid py-4 w-100 mx-auto" style="max-width: 1000px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0 fw-bold">Grupos da Instância</h4>
                    <button class="btn btn-success btn-sm w-auto fw-semibold rounded-pill px-3 shadow-sm"
                        id="btn-sync-groups" onclick="syncGroups()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Grupos
                    </button>
                </div>
                <div id="sync-groups-status" class="small mb-3 text-center"></div>
                <div id="groups-list" class="table-responsive shadow-sm rounded-3">
                    <div class="text-center text-muted py-5 bg-white border rounded">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Clique em "Atualizar Grupos" para carregar
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Sidebar Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebar">
        <div class="offcanvas-header text-white" style="background: var(--wa-primary);">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-sliders text-white-50 me-2"></i> Painel de Controle</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" style="background: var(--wa-bg);">

            <!-- Send Message Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-chat-text text-success me-2 fs-5"></i> Enviar Mensagem
                    </h6>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Número Destino</label>
                        <input type="text" class="form-control form-control-sm" id="send-number"
                            placeholder="Ex: 5511999999999">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Mensagem</label>
                        <textarea class="form-control form-control-sm" id="send-text" rows="3"
                            placeholder="Sua mensagem..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-clock me-1"></i>
                            Agendamento</label>
                        <select class="form-select form-select-sm" id="msg-schedule">
                            <option value="now">🟢 Enviar Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+10min">Daqui a 10 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="+2hours">Daqui a 2 horas</option>
                            <option value="tomorrow_8">🌅 Amanhã às 8h</option>
                            <option value="tomorrow_same">🔄 Amanhã neste horário</option>
                        </select>
                    </div>
                    <button class="btn btn-success w-100 fw-bold shadow-sm" onclick="sendMessage()" id="btn-send">
                        <i class="bi bi-send me-1"></i> Enviar
                    </button>
                    <div id="send-status" class="mt-2 text-center small fw-medium"></div>
                </div>
            </div>

            <!-- Send Status Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-circle text-primary me-2 fs-5"></i> Publicar Status
                    </h6>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Tipo</label>
                        <select class="form-select form-select-sm" id="status-type" onchange="toggleStatusFields()">
                            <option value="text">Texto</option>
                            <option value="image">Imagem</option>
                            <option value="video">Vídeo</option>
                            <option value="audio">Áudio</option>
                        </select>
                    </div>

                    <div id="status-text-fields" class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-semibold">Fundo</label>
                            <select class="form-select form-select-sm" id="status-bg-color">
                                <option value="1">Amarelo 1</option>
                                <option value="4">Verde 1</option>
                                <option value="7">Azul 1</option>
                                <option value="10">Lilás 1</option>
                                <option value="13">Magenta</option>
                                <option value="16">Marrom</option>
                                <option value="19" selected>Cinza Escuro</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-semibold">Fonte</label>
                            <select class="form-select form-select-sm" id="status-font">
                                <option value="0">Padrão</option>
                                <option value="1" selected>Estilo 1</option>
                                <option value="2">Estilo 2</option>
                            </select>
                        </div>
                    </div>

                    <div id="status-media-fields" class="mb-3 d-none">
                        <label class="form-label small text-muted fw-semibold">URL da Mídia</label>
                        <input type="text" class="form-control form-control-sm" id="status-file"
                            placeholder="https://exemplo.com/midia.jpg">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Texto / Legenda</label>
                        <textarea class="form-control form-control-sm" id="status-text" rows="2"
                            placeholder="O que está acontecendo?"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-clock me-1"></i>
                            Agendamento</label>
                        <select class="form-select form-select-sm" id="status-schedule">
                            <option value="now">🟢 Publicar Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="tomorrow_8">🌅 Amanhã às 8h</option>
                        </select>
                    </div>

                    <button class="btn btn-primary w-100 fw-bold shadow-sm" onclick="sendStatus()" id="btn-send-status">
                        <i class="bi bi-broadcast me-1"></i> Publicar Status
                    </button>
                    <div id="status-send-status" class="mt-2 text-center small fw-medium"></div>
                </div>
            </div>

            <!-- Schedules Sync Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-calendar-event text-info me-2 fs-5"></i> Agendamentos Ativos
                    </h6>
                    <button class="btn btn-outline-info btn-sm w-100 fw-semibold mb-3" onclick="loadSchedules()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Lista
                    </button>
                    <div id="schedules-list" class="small text-muted text-center pt-2">Clique em Atualizar para buscar.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

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
                const res = await fetch('index.php?action=get_instances');
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
                const res = await fetch('index.php?action=sync_instances');
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
                const res = await fetch('index.php?action=get_instances');
                instancesData = await res.json();
                const instanceName = "<?= htmlspecialchars($activeInstanceName ?? '')?>";
                if (instanceName) {
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
            document.getElementById('monitor').innerHTML = '<div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Carregando mensagens...</div>';
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

            const number = document.getElementById('send-number').value.trim();
            const text = document.getElementById('send-text').value.trim();
            const schedule = document.getElementById('msg-schedule').value;
            const statusDiv = document.getElementById('send-status');

            if (!number || !text) return alert("Preencha número e mensagem.");

            const action = schedule === 'now' ? 'send_message' : 'schedule_message';
            const payload = { instance_name: activeInstance, number, text };
            if (schedule !== 'now') {
                payload.schedule = schedule;
            }

            statusDiv.innerHTML = schedule === 'now' ? 'Enviando...' : 'Agendando...';

            try {
                const res = await fetch(`index.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    statusDiv.innerHTML = schedule === 'now' ? '<span style="color: green;">✔ Enviado!</span>' : '<span style="color: green;">✔ Agendado!</span>';
                    document.getElementById('send-text').value = '';
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

    </script>
</body>

</html>