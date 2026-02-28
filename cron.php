<?php
// cron.php — EXECUTAR VIA CRON A CADA MINUTO
// Crontab: * * * * * php /caminho/para/cron.php >> /caminho/para/cron.log 2>&1

require_once __DIR__ . '/db.php';

// Definir fuso horário padrão solicitado
date_default_timezone_set('America/Belem');

$API_BASE_URL = "https://sspeixoto.uazapi.com";

// =============================================
// FASE 1: Gerar mensagens para agentes ativos sem agendamento pendente
// =============================================
$jsonPath = '$.agent_id';
$sqlOrphan = "SELECT a.* FROM uazapi_agents a
    WHERE a.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM uazapi_schedule s
        WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(s.payload, ?)) AS UNSIGNED) = a.id
        AND s.status IN ('pending', 'paused')
    )";
$stmtOrphanAgents = $pdo->prepare($sqlOrphan);
$stmtOrphanAgents->execute([$jsonPath]);
$orphanAgents = $stmtOrphanAgents->fetchAll(PDO::FETCH_ASSOC);

if (!empty($orphanAgents)) {
    require_once __DIR__ . '/opencode_api.php';

    foreach ($orphanAgents as $agent) {
        $agentId = $agent['id'];

        $history = getAgentMessageHistory($pdo, $agentId, $agent['instance_name'], 10);
        $aiResult = generateOpenCodeMessage($agent['prompt'], $history);
        if (!$aiResult['success']) {
            echo date('Y-m-d H:i:s') . " [ERROR] Agent #$agentId falhou ao gerar msg: " . $aiResult['error'] . "\n";
            continue;
        }

        $nextText = $aiResult['message'];
        $nextStatus = $agent['requires_review'] ? 'paused' : 'pending';

        $interval = (int)$agent['interval_minutes'];
        $baseTime = !empty($agent['last_exec_at'])
            ? new DateTime($agent['last_exec_at'], new DateTimeZone('America/Sao_Paulo'))
            : new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

        $nextTime = clone $baseTime;
        $nextTime->modify("+$interval minutes");

        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        if ($nextTime < $now) {
            $nextTime = clone $now;
            $nextTime->modify('+1 minute');
        }

        if (!empty($agent['restricted_hours']) && strpos($agent['restricted_hours'], '-') !== false) {
            list($rStart, $rEnd) = explode('-', $agent['restricted_hours']);
            $currentHourMin = $nextTime->format('H:i');
            $tNow = strtotime($currentHourMin);
            $tStart = strtotime(trim($rStart));
            $tEnd = strtotime(trim($rEnd));

            $isRestricted = false;
            if ($tStart > $tEnd) {
                if ($tNow >= $tStart || $tNow <= $tEnd)
                    $isRestricted = true;
            }
            else {
                if ($tNow >= $tStart && $tNow <= $tEnd)
                    $isRestricted = true;
            }

            if ($isRestricted) {
                $nextTime = new DateTime($nextTime->format('Y-m-d') . ' ' . trim($rEnd), new DateTimeZone('America/Sao_Paulo'));
                if ($tStart > $tEnd && $tNow >= $tStart) {
                    $nextTime->modify('+1 day');
                }
                $nextTime->modify('+1 minute');
            }
        }

        $scheduledAt = $nextTime->format('Y-m-d H:i:s');
        $nextPayload = [
            'number' => $agent['recipient'],
            'text' => $nextText,
            'agent_id' => $agent['id']
        ];

        $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, ?)");
        $stmtSched->execute([$agent['instance_name'], json_encode($nextPayload), $scheduledAt, $nextStatus]);
        $pdo->prepare("UPDATE uazapi_agents SET last_exec_at = NOW() WHERE id = ?")->execute([$agentId]);

        echo date('Y-m-d H:i:s') . " [AGENT] Agent #$agentId (" . $agent['name'] . ") — nova msg agendada para: $scheduledAt (status: $nextStatus).\n";
    }
}

// =============================================
// FASE 2: Processar agendamentos pendentes
// =============================================
$stmt = $pdo->prepare("SELECT s.*, i.token FROM uazapi_schedule s JOIN uazapi_instances i ON s.instance_name = i.name WHERE s.status = 'pending' AND s.scheduled_at <= NOW() ORDER BY s.scheduled_at ASC LIMIT 10");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tasks)) {
    exit;
}

$updateStmt = $pdo->prepare("UPDATE uazapi_schedule SET status = ?, result = ?, executed_at = NOW() WHERE id = ?");

foreach ($tasks as $task) {
    $payload = json_decode($task['payload'], true);

    if (!$payload || empty($task['token'])) {
        $updateStmt->execute(['failed', 'Payload invalido ou token ausente', $task['id']]);
        continue;
    }

    $endpoint = '';
    if ($task['task_type'] === 'status') {
        $endpoint = '/send/status';
    }
    elseif ($task['task_type'] === 'message') {
        $endpoint = '/send/text';
    }
    else {
        $updateStmt->execute(['failed', 'Tipo desconhecido: ' . $task['task_type'], $task['id']]);
        continue;
    }

    $ch = curl_init($API_BASE_URL . $endpoint);
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
        echo date('Y-m-d H:i:s') . " [OK] Task #" . $task['id'] . " (" . $task['task_type'] . ") enviado.\n";

        // Se for mensagem de AI Agent, gerar a proxima
        if (isset($payload['agent_id'])) {
            require_once __DIR__ . '/opencode_api.php';
            $agentId = $payload['agent_id'];

            $stmtAgent = $pdo->prepare("SELECT * FROM uazapi_agents WHERE id = ? AND status = 'active'");
            $stmtAgent->execute([$agentId]);
            $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);

            if ($agent) {
                $history = getAgentMessageHistory($pdo, $agentId, $agent['instance_name'], 10);
                $aiResult = generateOpenCodeMessage($agent['prompt'], $history);
                if ($aiResult['success']) {
                    $nextText = $aiResult['message'];
                    $nextStatus = $agent['requires_review'] ? 'paused' : 'pending';

                    $interval = (int)$agent['interval_minutes'];
                    $nextDt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                    $nextDt->modify("+$interval minutes");

                    if (!empty($agent['restricted_hours']) && strpos($agent['restricted_hours'], '-') !== false) {
                        list($rStart, $rEnd) = explode('-', $agent['restricted_hours']);
                        $curHM = $nextDt->format('H:i');
                        $tNow2 = strtotime($curHM);
                        $tStart2 = strtotime(trim($rStart));
                        $tEnd2 = strtotime(trim($rEnd));

                        $isRestricted2 = false;
                        if ($tStart2 > $tEnd2) {
                            if ($tNow2 >= $tStart2 || $tNow2 <= $tEnd2)
                                $isRestricted2 = true;
                        }
                        else {
                            if ($tNow2 >= $tStart2 && $tNow2 <= $tEnd2)
                                $isRestricted2 = true;
                        }

                        if ($isRestricted2) {
                            $nextDt = new DateTime($nextDt->format('Y-m-d') . ' ' . trim($rEnd), new DateTimeZone('America/Sao_Paulo'));
                            if ($tStart2 > $tEnd2 && $tNow2 >= $tStart2) {
                                $nextDt->modify('+1 day');
                            }
                            $nextDt->modify('+1 minute');
                        }
                    }

                    $scheduledAt = $nextDt->format('Y-m-d H:i:s');
                    $nextPayload = [
                        'number' => $agent['recipient'],
                        'text' => $nextText,
                        'agent_id' => $agent['id']
                    ];

                    $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, ?)");
                    $stmtSched->execute([$agent['instance_name'], json_encode($nextPayload), $scheduledAt, $nextStatus]);
                    $pdo->prepare("UPDATE uazapi_agents SET last_exec_at = NOW() WHERE id = ?")->execute([$agentId]);

                    echo date('Y-m-d H:i:s') . " [OK] Agent #$agentId proxima msg agendada para: $scheduledAt.\n";
                }
                else {
                    echo date('Y-m-d H:i:s') . " [ERROR] Agent #$agentId falhou ao gerar msg: " . $aiResult['error'] . ".\n";
                }
            }
        }
    }
    else {
        $updateStmt->execute(['failed', "HTTP $httpCode: $response", $task['id']]);
        echo date('Y-m-d H:i:s') . " [FAIL] Task #" . $task['id'] . " HTTP $httpCode.\n";
    }
}
?>