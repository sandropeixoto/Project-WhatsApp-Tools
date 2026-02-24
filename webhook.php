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

    // Captura automaticamente o nome da instância que gerou o evento direto do payload
    $instanceName = $data['instanceName'] ?? ($data['data']['instanceName'] ?? 'default');

    // --- Auto-download de mídia ---
    // Se for uma mensagem de mídia, tenta baixar e injetar o fileURL no payload
    $msgData = $data['data'] ?? $data;
    $message = $msgData['message'] ?? null;

    if ($message && in_array($message['messageType'] ?? '', $MEDIA_TYPES) && empty($message['fileURL'])) {
        $messageId = $message['Id'] ?? ($message['id'] ?? '');

        if (!empty($messageId)) {
            // Buscar o token da instância
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
                        // Injetar fileURL no payload antes de salvar
                        if (isset($data['data']['message'])) {
                            $data['data']['message']['fileURL'] = $dlData['fileURL'];
                        }
                        else if (isset($data['message'])) {
                            $data['message']['fileURL'] = $dlData['fileURL'];
                        }
                        // Atualizar o payload serializado
                        $payload = json_encode($data);
                    }
                }
            }
        }
    }

    // Salvar log no banco
    $stmt = $pdo->prepare("INSERT INTO uazapi_logs (instance_name, event_type, payload) VALUES (?, ?, ?)");
    $stmt->execute([$instanceName, $eventType, $payload]);

    // Limpa logs antigos para manter o banco leve (mantém os últimos 500 por instância)
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
        $groupJid = $message['chatJid'] ?? ($msgData['chat']['wa_chatid'] ?? null);
        $groupName = $message['groupName'] ?? ($msgData['chat']['name'] ?? null);

        if ($groupJid) {
            $checkStmt = $pdo->prepare("SELECT jid FROM uazapi_groups WHERE jid = ? AND instance_name = ?");
            $checkStmt->execute([$groupJid, $instanceName]);

            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("INSERT IGNORE INTO uazapi_groups (jid, instance_name, name) VALUES (?, ?, ?)");
                $insertStmt->execute([$groupJid, $instanceName, $groupName]);
            }
        }
    }
}
?>