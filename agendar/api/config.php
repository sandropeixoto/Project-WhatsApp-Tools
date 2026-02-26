<?php
// agendar/api/config.php
require_once __DIR__ . '/auth_middleware.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($user['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas o dono da conta pode acessar configurações']);
    exit;
}

if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT name, instance_name, target_jid FROM agendar_accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo->prepare("SELECT id, phone_number, role, created_at FROM agendar_users WHERE account_id = ?");
    $stmtUsers->execute([$accountId]);
    $usersCount = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['account' => $account, 'users' => $usersCount]);
    exit;
}

if ($action === 'update') {
    $instanceName = $input['instance_name'] ?? null;
    $targetJid = $input['target_jid'] ?? null;

    $stmt = $pdo->prepare("UPDATE agendar_accounts SET instance_name = ?, target_jid = ? WHERE id = ?");
    $stmt->execute([$instanceName, $targetJid, $accountId]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'invite') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Telefone inválido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM agendar_users WHERE phone_number = ? AND account_id = ?");
    $stmt->execute([$phone, $accountId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuário já está nesta conta']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO agendar_users (account_id, phone_number, role) VALUES (?, ?, 'guest')");
    $stmt->execute([$accountId, $phone]);

    // Fetch instance details to construct the message
    $stmtInst = $pdo->prepare("SELECT instance_name FROM agendar_accounts WHERE id = ?");
    $stmtInst->execute([$accountId]);
    $accInfo = $stmtInst->fetch(PDO::FETCH_ASSOC);
    $instanceName = $accInfo['instance_name'] ?? 'sua equipe';

    // Prepare WhatsApp message payload
    require_once __DIR__ . '/../env.php';

    // Find base URL to provide the link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainPath = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');

    $message = "Olá! A conta *{$instanceName}* está lhe convidando para ajudar no agendamento de postagens utilizando o sistema Agendar.\n\nPara aceitar e participar da equipe, acesse o link abaixo e informe seu número de WhatsApp no momento do login:\n\n🔗 {$domainPath}";

    $payload = [
        'number' => $phone,
        'text' => $message
    ];

    // Ensure we load correct instances
    $API_BASE_URL = "https://sspeixoto.uazapi.com";
    $GLOBAL_API_KEY = "429683C4C977415CAC4093722755E482";

    $ch = curl_init("{$API_BASE_URL}/send/text");

    $headers = [
        'Content-Type: application/json',
        "token: {$TOKEN_INSTANCE_NAME}" // Using the core notification sender instance from env
    ];

    if (!empty($GLOBAL_API_KEY)) {
        $headers[] = "apikey: {$GLOBAL_API_KEY}";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_exec($ch);
    curl_close($ch);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove_user') {
    $id = $input['id'] ?? 0;
    if ($id == $userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Você não pode remover a si mesmo']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM agendar_users WHERE id = ? AND account_id = ? AND role = 'guest'");
    $stmt->execute([$id, $accountId]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);