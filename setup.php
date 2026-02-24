<?php
// setup.php

// ⚠️ IMPORTANTE: Troque esta URL pela URL pública onde seu webhook.php está hospedado!
$seuWebhookUrl = "https://sspeixoto.com.br/whatsapp/webhook.php"; 

$apiUrl = "https://free.uazapi.com/webhook";
$instanceToken = "b6138cd1-dff2-4f51-abcc-c2764f72cdc9";

$payload = [
    "url" => $seuWebhookUrl,
    // Monitorando todas as principais atividades da conta:
    "events" => ["messages", "connection", "presence", "history", "groups", "contacts"],
    // Prevenção de loop recomendada na documentação:
    "excludeMessages" => ["wasSentByApi"] 
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'token: ' . $instanceToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: " . $httpCode . "<br>";
echo "Resposta da API: " . $response;
?>