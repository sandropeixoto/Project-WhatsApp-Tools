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