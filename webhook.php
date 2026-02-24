<?php
// webhook.php
http_response_code(200);

$dbPath = __DIR__ . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cria a nova tabela de logs se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    instance_name TEXT, 
    event_type TEXT, 
    payload TEXT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

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
    $pdo->exec("DELETE FROM uazapi_logs WHERE id NOT IN (
        SELECT id FROM uazapi_logs WHERE instance_name = '{$instanceName}' ORDER BY id DESC LIMIT 500
    )");
}
?>