<?php
// cron.php — EXECUTAR VIA CRON A CADA MINUTO
// Crontab: * * * * * php /caminho/para/cron.php >> /caminho/para/cron.log 2>&1

require_once __DIR__ . '/db.php';

$API_BASE_URL = "https://sspeixoto.uazapi.com";

// Buscar agendamentos pendentes que já devem ser executados
$stmt = $pdo->prepare("SELECT s.*, i.token 
    FROM uazapi_schedule s 
    JOIN uazapi_instances i ON s.instance_name = i.name 
    WHERE s.status = 'pending' AND s.scheduled_at <= NOW() 
    ORDER BY s.scheduled_at ASC 
    LIMIT 10");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tasks)) {
    exit; // Nada para processar
}

$updateStmt = $pdo->prepare("UPDATE uazapi_schedule SET status = ?, result = ?, executed_at = NOW() WHERE id = ?");

foreach ($tasks as $task) {
    $payload = json_decode($task['payload'], true);

    if (!$payload || empty($task['token'])) {
        $updateStmt->execute(['failed', 'Payload inválido ou token ausente', $task['id']]);
        continue;
    }

    // Determinar o endpoint com base no task_type
    $endpoint = '';
    if ($task['task_type'] === 'status') {
        $endpoint = '/send/status';
    }
    else {
        $updateStmt->execute(['failed', 'Tipo de tarefa desconhecido: ' . $task['task_type'], $task['id']]);
        continue;
    }

    // Enviar via API
    $ch = curl_init("{$API_BASE_URL}{$endpoint}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'token: ' . $task['token']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $updateStmt->execute(['sent', $response, $task['id']]);
        echo date('Y-m-d H:i:s') . " [OK] Task #{$task['id']} ({$task['task_type']}) enviado.\n";
    }
    else {
        $updateStmt->execute(['failed', "HTTP {$httpCode}: {$response}", $task['id']]);
        echo date('Y-m-d H:i:s') . " [FAIL] Task #{$task['id']} HTTP {$httpCode}.\n";
    }
}
?>