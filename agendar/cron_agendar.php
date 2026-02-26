<?php
// agendar/cron_agendar.php
// Este script deve ser chamado via Cron Job a cada minuto:
// * * * * * php /Users/sandropeixoto/Dev/Project-WhatsApp-Tools/agendar/cron_agendar.php >> /var/log/cron_agendar.log 2>&1

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/env.php';

// Limita execução para evitar processos paralelos se houver lentidão
$lockFile = sys_get_temp_dir() . '/cron_agendar.lock';
$lockHandle = fopen($lockFile, 'w');
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Cron já está em execução.\n";
    exit;
}

try {
    // 1. Buscar mensagens pendentes
    $stmt = $pdo->prepare("
        SELECT m.*, a.instance_name, a.target_jid 
        FROM agendar_messages m
        JOIN agendar_accounts a ON m.account_id = a.id
        WHERE m.status = 'PENDING' AND m.scheduled_at <= NOW()
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Encontradas " . count($messages) . " mensagens pendentes.\n";

    foreach ($messages as $msg) {
        $id = $msg['id'];
        $instanceName = trim($msg['instance_name']);
        $targetJid = trim($msg['target_jid']);
        $mediaType = $msg['media_type'];
        $text = $msg['text'];
        $mediaPath = $msg['media_path'];

        if (empty($instanceName) || empty($targetJid)) {
            updateStatus($id, 'ERROR', 'Conta não configurada: Instância ou Destino ausente.');
            continue;
        }

        // Prepara CURL payload e url
        $endpoint = ($mediaType === 'text') ? '/send/text' : '/send/media';
        $url = rtrim($API_BASE_URL, '/') . $endpoint;

        $payload = [
            'number' => $targetJid,
            'text' => $text,
            'delay' => 2000
        ];

        if ($mediaType !== 'text') {
            $payload['type'] = $mediaType;

            // Construir o absolute path local
            $fullMediaPath = __DIR__ . '/../' . ltrim($mediaPath, '/');

            if (!empty($mediaPath) && file_exists($fullMediaPath)) {
                $mimeType = mime_content_type($fullMediaPath);
                $base64 = base64_encode(file_get_contents($fullMediaPath));
                $payload['file'] = "data:{$mimeType};base64,{$base64}";
            }
            else {
                updateStatus($id, 'ERROR', 'Arquivo de mídia não encontrado no disco.');
                continue;
            }
        }

        // Buscar o token da instância na tabela principal
        $stmtInst = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmtInst->execute([$instanceName]);
        $instance = $stmtInst->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            updateStatus($id, 'ERROR', "Token da instância '{$instanceName}' não encontrado.");
            continue;
        }

        // Adiciona headers necessários
        $headers = [
            "Content-Type: application/json",
            "token: " . $instance['token']
        ];

        if (!empty($GLOBAL_API_KEY)) {
            $headers[] = "apikey: {$GLOBAL_API_KEY}";
        }

        // Disparo
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            updateStatus($id, 'SENT');
        }
        else {
            $errMsg = "HTTP {$httpCode} - Retorno: " . ($response ? $response : $error);
            updateStatus($id, 'ERROR', substr($errMsg, 0, 500));
        }
    }

}
catch (Exception $e) {
    echo "Erro inesperado: " . $e->getMessage() . "\n";
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

function updateStatus($id, $status, $errorMessage = null)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE agendar_messages SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $errorMessage, $id]);
    echo "Mensagem {$id} atualizada para {$status}.\n";
}