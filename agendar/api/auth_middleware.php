<?php
// agendar/api/auth_middleware.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';

$user = null;

if (isset($_SESSION['agendar_user'])) {
    $user = $_SESSION['agendar_user'];
}
else if (isset($_COOKIE['agendar_session'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, a.instance_name 
        FROM agendar_sessions s
        JOIN agendar_users u ON s.user_id = u.id
        JOIN agendar_accounts a ON u.account_id = a.id
        WHERE s.id = ? AND s.expires_at >= NOW()
    ");
    $stmt->execute([$_COOKIE['agendar_session']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['agendar_user'] = $user;
    }
}

if (!$user) {
    if (!isset($skip_auth_redirect)) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado. Faça o login.']);
        exit;
    }
}

$accountId = $user['account_id'] ?? null;
$userId = $user['id'] ?? null;