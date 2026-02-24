<?php
// opencode_api.php

function generateOpenCodeMessage($prompt)
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

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Você é um assistente responsável por redigir mensagens concisas, no idioma do prompt fornecido, com uma linguagem natural e empática para envio em aplicativos de mensagens como WhatsApp. Não utilize formatação markdown agressiva, apenas texto simples, emojis se adequado, e siga estritamente o tema solicitado.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
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