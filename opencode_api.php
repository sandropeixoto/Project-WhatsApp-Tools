<?php
// opencode_api.php

function generateOpenCodeMessage($prompt, $previousMessages = [])
{
    if (!file_exists(__DIR__ . '/config.php')) {
        return ['success' => false, 'error' => 'Arquivo config.php ausente.'];
    }

    $config = require __DIR__ . '/config.php';
    if (!isset($config['api_key']) || empty($config['api_key'])) {
        return ['success' => false, 'error' => 'Chave da API ausente no config.php.'];
    }

    $url = $config['api_url'] ?? 'https://opencode.ai/zen/v1/chat/completions';
    $apiKey = $config['api_key'];
    $model = $config['model'] ?? 'gpt-5-nano';

    // System prompt com instrução anti-repetição
    $systemContent = 'Você é um assistente responsável por redigir mensagens concisas, no idioma do prompt fornecido, com uma linguagem natural e empática para envio em aplicativos de mensagens como WhatsApp. Não utilize formatação markdown agressiva, apenas texto simples, emojis se adequado, e siga estritamente o tema solicitado.';

    if (!empty($previousMessages)) {
        $systemContent .= "\n\nIMPORTANTE: Abaixo estão mensagens que você já escreveu anteriormente sobre este mesmo tema. Você DEVE criar uma mensagem completamente NOVA e DIFERENTE. Não repita ideias, frases, estruturas ou abordagens similares. Seja criativo e surpreenda com uma perspectiva ou ângulo diferente.";
    }

    $messages = [
        ['role' => 'system', 'content' => $systemContent]
    ];

    // Injetar mensagens anteriores como histórico (role: assistant)
    foreach ($previousMessages as $prevMsg) {
        if (!empty($prevMsg)) {
            $messages[] = ['role' => 'assistant', 'content' => $prevMsg];
        }
    }

    // Prompt do usuário
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $payload = [
        'model' => $model,
        'messages' => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode >= 400) {
        return ['success' => false, 'error' => $error ?: "Erro HTTP: $httpCode - " . $response];
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Resposta inesperada da API: ' . $response];
    }

    return ['success' => true, 'message' => trim($data['choices'][0]['message']['content'])];
}

/**
 * Busca as últimas N mensagens geradas por um agente no banco de dados.
 * Usa a tabela uazapi_schedule que já contém o texto no payload JSON.
 */
function getAgentMessageHistory($pdo, $agentId, $limit = 10)
{
    $jsonPath = '$.agent_id';
    $textPath = '$.text';
    $stmt = $pdo->prepare("
        SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) as msg_text
        FROM uazapi_schedule
        WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) AS UNSIGNED) = ?
        AND JSON_EXTRACT(payload, ?) IS NOT NULL
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->execute([$textPath, $jsonPath, $agentId, $textPath, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Reverter para ordem cronológica (mais antiga primeiro)
    return array_reverse($rows);
}