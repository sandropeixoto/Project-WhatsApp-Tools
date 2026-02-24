<?php
// webhook.php
http_response_code(200);

require_once __DIR__ . '/db.php';

// --- CONFIGURAÇÕES DA API ---
$API_BASE_URL = "https://sspeixoto.uazapi.com";

// Tipos de mensagem de mídia que precisam de download
$MEDIA_TYPES = ['ImageMessage', 'VideoMessage', 'AudioMessage', 'PTTMessage', 'StickerMessage', 'DocumentMessage', 'GifMessage'];

$payload = file_get_contents('php://input');

if (!empty($payload)) {
    $data = json_decode($payload, true);

    // Captura o tipo de evento
    $eventType = $data['EventType'] ?? ($data['event'] ?? 'unknown');

    // Captura o nome da instância
    $instanceName = $data['instanceName'] ?? ($data['data']['instanceName'] ?? 'default');

    // --- Extrair campos da mensagem ---
    $msgData = $data['data'] ?? $data;
    $message = $msgData['message'] ?? null;
    $chat = $msgData['chat'] ?? [];

    $messageId = null;
    $chatJid = null;
    $senderJid = null;
    $senderName = null;
    $isGroup = 0;
    $fromMe = 0;
    $messageType = null;
    $messageTimestamp = null;
    $text = null;
    $fileUrl = null;
    $mimetype = null;
    $fileName = null;
    $quotedId = null;
    $groupName = null;
    $chatName = null;

    if ($message && is_array($message)) {
        $messageId = $message['Id'] ?? ($message['id'] ?? ($message['messageid'] ?? null));
        $chatJid = $message['chatJid'] ?? ($chat['wa_chatid'] ?? null);
        $senderJid = $message['sender_pn'] ?? ($message['sender'] ?? null);
        $senderName = $message['senderName'] ?? null;
        $isGroup = !empty($message['isGroup']) ? 1 : 0;
        $fromMe = !empty($message['fromMe']) ? 1 : 0;
        $messageType = $message['messageType'] ?? null;
        $messageTimestamp = $message['messageTimestamp'] ?? null;
        $text = $message['text'] ?? null;
        $fileUrl = $message['fileURL'] ?? null;
        $quotedId = $message['quoted'] ?? null;
        $groupName = $message['groupName'] ?? ($isGroup ? ($chat['name'] ?? null) : null);
        $chatName = $chat['name'] ?? null;

        $content = $message['content'] ?? [];
        if (is_array($content)) {
            $mimetype = $content['Mimetype'] ?? null;
            $fileName = $content['FileName'] ?? null;
        }

        // --- Auto-download de mídia ---
        if (in_array($messageType, $MEDIA_TYPES) && empty($fileUrl) && !empty($messageId)) {
            $tokenStmt = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
            $tokenStmt->execute([$instanceName]);
            $instanceRow = $tokenStmt->fetch(PDO::FETCH_ASSOC);

            if ($instanceRow) {
                $downloadPayload = json_encode([
                    'id' => $messageId,
                    'return_link' => true,
                    'return_base64' => false
                ]);

                $ch = curl_init("{$API_BASE_URL}/message/download");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $downloadPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'token: ' . $instanceRow['token']
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);

                $dlResponse = curl_exec($ch);
                $dlHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($dlHttpCode === 200 && !empty($dlResponse)) {
                    $dlData = json_decode($dlResponse, true);
                    if (!empty($dlData['fileURL'])) {
                        $fileUrl = $dlData['fileURL'];
                        $mimetype = $dlData['mimetype'] ?? $mimetype;

                        // Injetar fileURL no payload antes de salvar
                        if (isset($data['data']['message'])) {
                            $data['data']['message']['fileURL'] = $fileUrl;
                        }
                        elseif (isset($data['message'])) {
                            $data['message']['fileURL'] = $fileUrl;
                        }
                        $payload = json_encode($data);
                    }
                }
            }
        }
    }

    // Salvar log no banco COM campos extraídos
    $stmt = $pdo->prepare("INSERT INTO uazapi_logs 
        (instance_name, event_type, payload, message_id, chat_jid, sender_jid, sender_name, is_group, from_me, message_type, message_timestamp, text, file_url, mimetype, file_name, quoted_id, group_name, chat_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $instanceName, $eventType, $payload,
        $messageId, $chatJid, $senderJid, $senderName,
        $isGroup, $fromMe, $messageType, $messageTimestamp,
        $text, $fileUrl, $mimetype, $fileName, $quotedId,
        $groupName, $chatName
    ]);

    // Limpa logs antigos (mantém os últimos 500 por instância)
    $pdo->exec("DELETE FROM uazapi_logs 
        WHERE instance_name = " . $pdo->quote($instanceName) . " 
        AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM uazapi_logs 
                WHERE instance_name = " . $pdo->quote($instanceName) . " 
                ORDER BY id DESC LIMIT 500
            ) AS keep_rows
        )");

    // --- Auto-registro de grupos via mensagens recebidas ---
    if ($message && !empty($message['isGroup'])) {
        $grpJid = $message['chatJid'] ?? ($chat['wa_chatid'] ?? null);
        $grpName = $message['groupName'] ?? ($chat['name'] ?? null);

        if ($grpJid) {
            $checkStmt = $pdo->prepare("SELECT jid FROM uazapi_groups WHERE jid = ? AND instance_name = ?");
            $checkStmt->execute([$grpJid, $instanceName]);

            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("INSERT IGNORE INTO uazapi_groups (jid, instance_name, name) VALUES (?, ?, ?)");
                $insertStmt->execute([$grpJid, $instanceName, $grpName]);
            }
        }
    }
}
?>