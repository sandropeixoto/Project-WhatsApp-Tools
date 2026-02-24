<?php
// backfill_logs.php — EXECUTAR VIA BROWSER PARA PREENCHER COLUNAS EXTRAÍDAS
// Processa logs legados que já estão no banco e extrai campos do JSON payload.
// Deletar este arquivo após o uso!

require_once __DIR__ . '/db.php';

set_time_limit(300); // 5 minutos max

echo "<pre>\n";
echo "=== Backfill: Extraindo campos dos logs legados ===\n\n";

try {
    // Buscar logs que têm event_type 'messages' e message_id ainda não preenchido
    $stmt = $pdo->query("SELECT id, payload FROM uazapi_logs WHERE message_id IS NULL AND payload IS NOT NULL ORDER BY id ASC");

    $updateStmt = $pdo->prepare("UPDATE uazapi_logs SET 
        message_id = ?,
        chat_jid = ?,
        sender_jid = ?,
        sender_name = ?,
        is_group = ?,
        from_me = ?,
        message_type = ?,
        message_timestamp = ?,
        text = ?,
        file_url = ?,
        mimetype = ?,
        file_name = ?,
        quoted_id = ?,
        group_name = ?,
        chat_name = ?
        WHERE id = ?");

    $processed = 0;
    $updated = 0;
    $skipped = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $processed++;
        $data = json_decode($row['payload'], true);

        if (!$data || !is_array($data)) {
            $skipped++;
            continue;
        }

        // Navegar na estrutura: data.message ou message diretamente
        $msgData = $data['data'] ?? $data;
        $message = $msgData['message'] ?? null;
        $chat = $msgData['chat'] ?? [];

        if (!$message || !is_array($message)) {
            $skipped++;
            continue;
        }

        // Extrair campos
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
        $groupName = $message['groupName'] ?? ($isGroup ? ($chat['name'] ?? null) : null);
        $chatName = $chat['name'] ?? null;
        $quotedId = $message['quoted'] ?? null;

        // Extrair mimetype e fileName do content
        $content = $message['content'] ?? [];
        $mimetype = null;
        $fileName = null;
        if (is_array($content)) {
            $mimetype = $content['Mimetype'] ?? null;
            $fileName = $content['FileName'] ?? null;
        }

        $updateStmt->execute([
            $messageId,
            $chatJid,
            $senderJid,
            $senderName,
            $isGroup,
            $fromMe,
            $messageType,
            $messageTimestamp,
            $text,
            $fileUrl,
            $mimetype,
            $fileName,
            $quotedId,
            $groupName,
            $chatName,
            $row['id']
        ]);

        $updated++;
    }

    echo "📊 Resultado:\n";
    echo "  Total processados: {$processed}\n";
    echo "  Atualizados: {$updated}\n";
    echo "  Ignorados (sem msg): {$skipped}\n";
    echo "\n🎉 Backfill concluído!\n";

}
catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>