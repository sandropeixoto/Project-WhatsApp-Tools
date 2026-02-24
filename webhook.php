<?php
// webhook.php
http_response_code(200);

require_once __DIR__ . '/db.php';

$payload = file_get_contents('php://input');

if (!empty($payload)) {
    $data = json_decode($payload, true);

    // Captura o tipo de evento
    $eventType = $data['EventType'] ?? ($data['event'] ?? 'unknown');

    // Captura automaticamente o nome da instância que gerou o evento direto do payload
    $instanceName = $data['instanceName'] ?? ($data['data']['instanceName'] ?? 'default');

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

    // --- Feature 4: Auto-registro de grupos via mensagens recebidas ---
    $msgData = $data['data'] ?? $data;
    $message = $msgData['message'] ?? null;

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