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
    elseif ($task['task_type'] === 'message') {
        $endpoint = '/send/text';
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

        // Verificar se é uma mensagem de um AI Agent para gerar a próxima
        if (isset($payload['agent_id'])) {
            require_once __DIR__ . '/opencode_api.php';
            $agentId = $payload['agent_id'];

            $stmtAgent = $pdo->prepare("SELECT * FROM uazapi_agents WHERE id = ? AND status = 'active'");
            $stmtAgent->execute([$agentId]);
            $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);

            if ($agent) {
                $aiResult = generateOpenCodeMessage($agent['prompt']);
                if ($aiResult['success']) {
                    $nextText = $aiResult['message'];
                    $nextStatus = $agent['requires_review'] ? 'paused' : 'pending';

                    // Calcular próximo horário baseado no interval_minutes
                    $interval = (int)$agent['interval_minutes'];
                    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                    $now->modify("+{$interval} minutes");

                    // Lógica de restricted_hours (ex: "22:00-08:00")
                    if (!empty($agent['restricted_hours']) && strpos($agent['restricted_hours'], '-') !== false) {
                        list($start, $end) = explode('-', $agent['restricted_hours']);
                        $currentHourMin = $now->format('H:i');

                        $startDt = DateTime::createFromFormat('H:i', trim($start), new DateTimeZone('America/Sao_Paulo'));
                        $endDt = DateTime::createFromFormat('H:i', trim($end), new DateTimeZone('America/Sao_Paulo'));
                        if ($startDt && $endDt) {
                            $timeNow = strtotime($currentHourMin);
                            $timeStart = strtotime(trim($start));
                            $timeEnd = strtotime(trim($end));

                            $isRestricted = false;
                            if ($timeStart > $timeEnd) { // Ex: 22:00-08:00
                                if ($timeNow >= $timeStart || $timeNow <= $timeEnd)
                                    $isRestricted = true;
                            }
                            else { // Ex: 08:00-12:00
                                if ($timeNow >= $timeStart && $timeNow <= $timeEnd)
                                    $isRestricted = true;
                            }

                            if ($isRestricted) {
                                // Redefine o horário para 1 minuto após o fim da restrição
                                $now = new DateTime($now->format('Y-m-d') . ' ' . trim($end), new DateTimeZone('America/Sao_Paulo'));
                                if ($timeStart > $timeEnd && $timeNow >= $timeStart) {
                                    $now->modify('+1 day');
                                }
                                $now->modify('+1 minute');
                            }
                        }
                    }

                    $scheduledAt = $now->format('Y-m-d H:i:s');
                    $nextPayload = [
                        'number' => $agent['recipient'],
                        'text' => $nextText,
                        'agent_id' => $agent['id']
                    ];

                    $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, ?)");
                    $stmtSched->execute([$agent['instance_name'], json_encode($nextPayload), $scheduledAt, $nextStatus]);

                    $pdo->prepare("UPDATE uazapi_agents SET last_exec_at = NOW() WHERE id = ?")->execute([$agentId]);

                    echo date('Y-m-d H:i:s') . " [OK] Agent #{$agentId} gerou próxima msg agendada para: {$scheduledAt}.\n";
                }
                else {
                    echo date('Y-m-d H:i:s') . " [ERROR] Agent #{$agentId} falhou ao gerar msg: {$aiResult['error']}.\n";
                }
            }
        }
    }
    else {
        $updateStmt->execute(['failed', "HTTP {$httpCode}: {$response}", $task['id']]);
        echo date('Y-m-d H:i:s') . " [FAIL] Task #{$task['id']} HTTP {$httpCode}.\n";
    }
}
?>