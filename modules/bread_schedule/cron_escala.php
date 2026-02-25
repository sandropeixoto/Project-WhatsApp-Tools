<?php
/**
 * CRON - Escala do Pão
 * 
 * Execução recomendada via Cron:
 * * * * * * php /caminho/para/modules/bread_schedule/cron_escala.php >> /caminho/para/cron_escala.log 2>&1
 */

// Navega 2 diretórios para trás para incluir os arquivos da raiz
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../opencode_api.php';

// ==========================================
// CONFIGURAÇÕES DO MÓDULO
// ==========================================
$INSTANCE_NAME = "Sandro"; // Ex: "WhatsApp_Principal"
$RECIPIENT = "559191387115-1470766677@g.us"; // Ex: "5511999999999@g.us"
$API_ESCALA = "https://escala-do-pao.web.app/api/schedule?days=5";

// Fuso horário
$tz = new DateTimeZone('America/Sao_Paulo');
$now = new DateTime('now', $tz);
$todayDate = $now->format('Y-m-d');
$currentTime = $now->format('H:i');

$amanhaDate = clone $now;
$amanhaDate->modify('+1 day');
$amanhaDateStr = $amanhaDate->format('Y-m-d');

// Vamos verificar qual cenário deve rodar dependendo do horário
$isSpoiler = ($currentTime === '16:00');
$isChamado = ($currentTime === '07:30');

// Para testes manuais (descomente para testar ignorando a hora)
// $isSpoiler = true;
// $isChamado = true;

if (!$isSpoiler && !$isChamado) {
    // Não é hora de rodar nenhum dos cenários.
    exit;
}

// 1. Buscar a Escala da API
$jsonData = @file_get_contents($API_ESCALA);
if (!$jsonData) {
    echo $now->format('Y-m-d H:i:s') . " [ERRO] Falha ao acessar a API da Escala do Pão.\n";
    exit;
}

$data = json_decode($jsonData, true);
$schedule = $data['schedule'] ?? [];

if (empty($schedule)) {
    echo $now->format('Y-m-d H:i:s') . " [ERRO] Escala vazia ou JSON inválido.\n";
    exit;
}

// Cenário 1: Spoiler (às 16:00)
if ($isSpoiler) {
    // Procura se hoje é um dia útil/escalado
    $todayIsScheduled = false;
    $todayIndex = -1;
    foreach ($schedule as $i => $item) {
        if ($item['date'] === $todayDate) {
            $todayIsScheduled = true;
            $todayIndex = $i;
            break;
        }
    }

    // Só enviamos spoiler se hoje for um dia da escala. 
    // Assim, enviamos o spoiler do próximo dia útil sempre no dia útil atual, evitando spam.
    if ($todayIsScheduled && isset($schedule[$todayIndex + 1])) {
        $nextItem = $schedule[$todayIndex + 1];

        $responsavel = $nextItem['responsible'];
        $dataProxima = $nextItem['date'];
        $diaSemana = $nextItem['weekday'];

        $next2 = [];
        if (isset($schedule[$todayIndex + 2])) {
            $next2[] = $schedule[$todayIndex + 2]['responsible'];
        }
        if (isset($schedule[$todayIndex + 3])) {
            $next2[] = $schedule[$todayIndex + 3]['responsible'];
        }

        // Formatar data proxima para dd/mm/yyyy para ficar amigável
        $dateObj = new DateTime($dataProxima);
        $dataAmigavel = $dateObj->format('d/m/Y');

        if (empty($next2)) {
            $nomesSeguintes = "a galera da fila";
        }
        else {
            $nomesSeguintes = implode(', ', $next2);
        }

        $prompt = "Crie uma mensagem divertida de 'spoiler' avisando que o(a) {$responsavel} trará o pão na próxima vez ({$diaSemana}, {$dataAmigavel}). Mencione que os próximos da fila são {$nomesSeguintes} para eles já irem se preparando. Use emojis e um tom descontraído.";
    }
    else {
        echo $now->format('Y-m-d H:i:s') . " [AVISO] Hoje não é dia de escala ou não há um próximo dia no JSON. Nenhum spoiler será enviado.\n";
    }
}

// Cenário 2: O Chamado (às 07:30)
if ($isChamado) {
    $infoItem = null;
    foreach ($schedule as $item) {
        if ($item['date'] === $todayDate) {
            $infoItem = $item;
            break;
        }
    }

    if (!$infoItem) {
        echo $now->format('Y-m-d H:i:s') . " [AVISO] Ninguém escalado para hoje ({$todayDate}). Não enviaremos o chamado.\n";
    }
    else {
        $nomeResponsavel = $infoItem['responsible'];
        $prompt = "Crie uma mensagem de bom dia cobrando o(a) {$nomeResponsavel}, dizendo que a equipe já está com a manteiga na mão e o café pronto esperando por ele(a) e o pão hoje! Seja engraçado e motivador.";
    }
}

// Se temos um prompt, chama a IA e agenda a mensagem
if (!empty($prompt)) {
    echo $now->format('Y-m-d H:i:s') . " [INFO] Gerando mensagem via IA...\n";

    // Podemos buscar o histórico de mensagens enviadas para evitar repetição
    // Como simplificação, não temos um agentId específico para esse cron, passaremos array vazio
    $aiResult = generateOpenCodeMessage($prompt, []);

    if (!$aiResult['success']) {
        echo $now->format('Y-m-d H:i:s') . " [ERRO] Falha na IA: " . $aiResult['error'] . "\n";
        exit;
    }

    $generatedText = "*🤖 ESCALA-DO-PAO-CGLC 🤖*\n" . $aiResult['message'];

    // Inserir na tabela de schedule para o cron.php principal enviar
    $scheduledAt = $now->format('Y-m-d H:i:s');
    $apiPayload = [
        'number' => $RECIPIENT,
        'text' => $generatedText
        // Não passamos agent_id pois não é vinculado a um Agente do painel de forma cíclica
    ];

    $stmtSched = $pdo->prepare("INSERT INTO uazapi_schedule (instance_name, task_type, payload, scheduled_at, status) VALUES (?, 'message', ?, ?, 'pending')");
    $stmtSched->execute([$INSTANCE_NAME, json_encode($apiPayload), $scheduledAt]);

    echo $now->format('Y-m-d H:i:s') . " [SUCESSO] Mensagem agendada! ID: " . $pdo->lastInsertId() . "\n";
    echo "Texto Gerado:\n------------\n{$generatedText}\n------------\n";
}