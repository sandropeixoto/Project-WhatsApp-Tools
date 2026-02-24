<?php
session_start();
require_once __DIR__ . '/db.php';

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
        $stmt = $pdo->prepare("UPDATE uazapi_schedule SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'paused')");
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

    // 10. AI Agents - CRUD e Agendamento
    if ($action === 'get_agents') {
        $stmt = $pdo->query("SELECT * FROM uazapi_agents ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'create_agent') {
        $instanceName = $input['instance_name'] ?? '';
        $name = $input['name'] ?? '';
        $prompt = $input['prompt'] ?? '';
        $recipient = $input['recipient'] ?? '';
        $intervalMinutes = (int)($input['interval_minutes'] ?? 60);
        $restrictedHours = $input['restricted_hours'] ?? '';
        $requiresReview = isset($input['requires_review']) ? (int)$input['requires_review'] : 1;

        if (empty($instanceName) || empty($name) || empty($prompt) || empty($recipient)) {
            http_response_code(400);
            echo json_encode(['error' => 'Preencha todos os campos obrigatórios.']);
            exit;
        }

        // 1. Criar o Agente no BD
        $stmt = $pdo->prepare("INSERT INTO uazapi_agents 
            (instance_name, name, prompt, recipient, interval_minutes, restricted_hours, requires_review, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $instanceName, $name, $prompt, $recipient, $intervalMinutes, $restrictedHours, $requiresReview
        ]);
        $agentId = $pdo->lastInsertId();

        // 2. Gerar a primeira mensagem com a IA
        require_once __DIR__ . '/opencode_api.php';
        $aiResult = generateOpenCodeMessage($prompt);

        if (!$aiResult['success']) {
            // Se falhou em gerar a primeira, ainda criamos o agente, mas retornamos aviso.
            echo json_encode(['success' => true, 'agent_id' => $agentId, 'warning' => 'Agente criado, mas falha ao gerar texto inicial: ' . $aiResult['error']]);
            exit;
        }

        // 3. Agendar a primeira mensagem
        $generatedText = $aiResult['message'];
        $scheduleStatus = $requiresReview ? 'paused' : 'pending';

        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        // A primeira mensagem é agendada para 1 minuto no futuro p/ dar tempo do usuário ver, ou enviar logo.
        $now->modify('+1 minute');
        $scheduledAt = $now->format('Y-m-d H:i:s');

        $apiPayload = [
            'number' => $recipient,
            'text' => $generatedText,
            'agent_id' => $agentId
        ];

        $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, ?)");
        $stmtSched->execute([$instanceName, json_encode($apiPayload), $scheduledAt, $scheduleStatus]);

        echo json_encode(['success' => true, 'agent_id' => $agentId, 'message' => 'Agente criado e primeira mensagem agendada.']);
        exit;
    }

    if ($action === 'update_agent_status') {
        $id = $input['id'] ?? 0;
        $status = $input['status'] ?? 'active';
        $stmt = $pdo->prepare("UPDATE uazapi_agents SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
        exit;
    }

    if ($action === 'edit_agent') {
        $id = $input['id'] ?? 0;
        $name = $input['name'] ?? '';
        $prompt = $input['prompt'] ?? '';
        $recipient = $input['recipient'] ?? '';
        $intervalMinutes = (int)($input['interval_minutes'] ?? 60);
        $restrictedHours = $input['restricted_hours'] ?? '';
        $requiresReview = isset($input['requires_review']) ? (int)$input['requires_review'] : 1;

        if (empty($id) || empty($name) || empty($prompt) || empty($recipient)) {
            http_response_code(400);
            echo json_encode(['error' => 'Preencha todos os campos obrigatórios.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE uazapi_agents SET 
            name = ?, prompt = ?, recipient = ?, interval_minutes = ?, restricted_hours = ?, requires_review = ?
            WHERE id = ?");
        $stmt->execute([$name, $prompt, $recipient, $intervalMinutes, $restrictedHours, $requiresReview, $id]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'force_generate_agent_message') {
        $agentId = $input['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM uazapi_agents WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            http_response_code(404);
            echo json_encode(['error' => 'Agente não encontrado.']);
            exit;
        }

        require_once __DIR__ . '/opencode_api.php';
        $aiResult = generateOpenCodeMessage($agent['prompt']);

        if (!$aiResult['success']) {
            echo json_encode(['error' => 'Falha ao gerar mensagem: ' . $aiResult['error']]);
            exit;
        }

        $generatedText = $aiResult['message'];
        $scheduleStatus = $agent['requires_review'] ? 'paused' : 'pending';

        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $now->modify('+1 minute');
        $scheduledAt = $now->format('Y-m-d H:i:s');

        $apiPayload = [
            'number' => $agent['recipient'],
            'text' => $generatedText,
            'agent_id' => $agent['id']
        ];

        $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, ?)");
        $stmtSched->execute([$agent['instance_name'], json_encode($apiPayload), $scheduledAt, $scheduleStatus]);

        echo json_encode(['success' => true, 'message' => 'Nova mensagem gerada e agendada.']);
        exit;
    }

    if ($action === 'delete_agent') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM uazapi_agents WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
        exit;
    }

    if ($action === 'update_schedule_status') {
        // Aproveitar para permitir aprovação de mensagens pausadas na aba de Agendamentos
        $id = $input['id'] ?? 0;
        $status = $input['status'] ?? 'pending';
        $stmt = $pdo->prepare("UPDATE uazapi_schedule SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
        exit;
    }
}
?> }
}
?>