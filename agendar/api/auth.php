<?php
// agendar/api/auth.php
session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../env.php';

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ação não especificada']);
    exit;
}

$action = $_GET['action'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'send_token') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');

    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Número de telefone inválido']);
        exit;
    }

    // Generate a 6-digit numeric token
    $token = sprintf("%06d", mt_rand(1, 999999));

    // Set expiration to 10 minutes from now
    $expiresAt = (new DateTime('now'))->modify('+10 minutes')->format('Y-m-d H:i:s');

    try {
        // Save token to DB
        $stmt = $pdo->prepare("INSERT INTO agendar_auth_tokens (phone_number, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $token, $expiresAt]);

        // Prepare API request to send the token via WhatsApp Tools existing API
        $API_BASE_URL = "https://sspeixoto.uazapi.com";

        // Local WA Tools instance check
        $stmtInst = $pdo->prepare("SELECT token FROM uazapi_instances WHERE name = ?");
        $stmtInst->execute([$TOKEN_INSTANCE_NAME]);
        $instance = $stmtInst->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            http_response_code(500);
            echo json_encode(['error' => "Instância de envio de token '{$TOKEN_INSTANCE_NAME}' não configurada ou não encontrada no sistema."]);
            exit;
        }

        $text = "🔢 *Seu código de acesso do Agendar:*\n\n{$token}\n\nVálido por 10 minutos.";
        $payload = [
            "number" => $phone,
            "text" => $text
        ];

        $ch = curl_init("{$API_BASE_URL}/send/text");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instance['token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            echo json_encode(['success' => true, 'message' => 'Token enviado com sucesso!']);
        }
        else {
            http_response_code($httpCode);
            echo json_encode(['error' => 'Falha ao enviar mensagem via WhatsApp API.', 'details' => $response]);
        }

    }
    catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados', 'details' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'verify_token') {
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $token = preg_replace('/[^0-9]/', '', $input['token'] ?? '');

    if (empty($phone) || empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Telefone e Token são obrigatórios']);
        exit;
    }

    try {
        // Find valid token
        $stmt = $pdo->prepare("SELECT * FROM agendar_auth_tokens WHERE phone_number = ? AND token = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$phone, $token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenRecord) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado. Tente gerar um novo.']);
            exit;
        }

        // Delete used token
        $pdo->prepare("DELETE FROM agendar_auth_tokens WHERE id = ?")->execute([$tokenRecord['id']]);

        // Find or create user
        $stmtUser = $pdo->prepare("SELECT * FROM agendar_users WHERE phone_number = ?");
        $stmtUser->execute([$phone]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Check if there's any active account? For now we just create a new Account for them as owner
            $pdo->beginTransaction();
            $stmtAcc = $pdo->prepare("INSERT INTO agendar_accounts (name) VALUES (?)");
            $stmtAcc->execute(["Conta de " . $phone]);
            $accountId = $pdo->lastInsertId();

            $stmtInsUser = $pdo->prepare("INSERT INTO agendar_users (account_id, phone_number, role) VALUES (?, ?, 'owner')");
            $stmtInsUser->execute([$accountId, $phone]);
            $userId = $pdo->lastInsertId();
            $pdo->commit();

            $user = [
                'id' => $userId,
                'account_id' => $accountId,
                'phone_number' => $phone,
                'role' => 'owner'
            ];
        }

        // Create 30-day session
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime('now'))->modify('+30 days')->format('Y-m-d H:i:s');

        $stmtSession = $pdo->prepare("INSERT INTO agendar_sessions (id, user_id, expires_at) VALUES (?, ?, ?)");
        $stmtSession->execute([$sessionId, $user['id'], $expiresAt]);

        // Set Cookie (valid for 30 days)
        setcookie('agendar_session', $sessionId, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        $_SESSION['agendar_user'] = $user;

        echo json_encode(['success' => true, 'redirect' => $BASE_URL . '/index.php']);
    }
    catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno', 'details' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'logout') {
    if (isset($_COOKIE['agendar_session'])) {
        $sessionId = $_COOKIE['agendar_session'];
        try {
            $stmt = $pdo->prepare("DELETE FROM agendar_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        }
        catch (Exception $e) {
        // Ignore DB errors on logout
        }
        setcookie('agendar_session', '', time() - 3600, '/');
    }
    session_destroy();
    header("Location: ../login.php");
    exit;
}