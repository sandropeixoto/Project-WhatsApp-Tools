<?php
// agendar/api/events.php
require_once __DIR__ . '/auth_middleware.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'list') {
    $start = $_GET['start'] ?? date('Y-m-d H:i:s', strtotime('-1 month'));
    $end = $_GET['end'] ?? date('Y-m-d H:i:s', strtotime('+1 month'));

    $stmt = $pdo->prepare("
        SELECT * FROM agendar_messages 
        WHERE account_id = ? 
        AND scheduled_at >= ? AND scheduled_at <= ?
    ");
    $stmt->execute([$accountId, $start, $end]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($messages as $m) {
        $color = '#3788d8'; // default blue
        if ($m['status'] === 'SENT')
            $color = '#28a745'; // green
        else if ($m['status'] === 'ERROR')
            $color = '#dc3545'; // red

        $events[] = [
            'id' => $m['id'],
            'title' => $m['media_type'] === 'text' ? mb_strimwidth($m['text'] ?: '(Sem texto)', 0, 30, '...') : '[' . strtoupper($m['media_type']) . ']',
            'start' => $m['scheduled_at'],
            'color' => $color,
            'extendedProps' => [
                'media_type' => $m['media_type'],
                'text' => $m['text'],
                'media_path' => $m['media_path'],
                'status' => $m['status'],
                'error_message' => $m['error_message']
            ]
        ];
    }
    echo json_encode($events);
    exit;
}

if ($action === 'create') {
    $mediaType = $input['media_type'] ?? 'text';
    $text = $input['text'] ?? '';
    $mediaPath = $input['media_path'] ?? null;
    $scheduledAt = $input['scheduled_at'] ?? '';

    if (empty($scheduledAt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Data e hora são obrigatórias']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO agendar_messages (account_id, user_id, media_type, text, media_path, scheduled_at, status)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
    ");
    $stmt->execute([$accountId, $userId, $mediaType, $text, $mediaPath, $scheduledAt]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($action === 'update') {
    $id = $input['id'] ?? 0;

    // Check ownership
    $stmt = $pdo->prepare("SELECT id, status FROM agendar_messages WHERE id = ? AND account_id = ?");
    $stmt->execute([$id, $accountId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        http_response_code(404);
        echo json_encode(['error' => 'Mensagem não encontrada']);
        exit;
    }

    if ($msg['status'] !== 'PENDING') {
        http_response_code(400);
        echo json_encode(['error' => 'Não é possível editar uma mensagem já enviada ou com erro']);
        exit;
    }

    // Update fields (only provided ones)
    $updates = [];
    $params = [];

    if (isset($input['media_type'])) {
        $updates[] = "media_type = ?";
        $params[] = $input['media_type'];
    }
    if (isset($input['text'])) {
        $updates[] = "text = ?";
        $params[] = $input['text'];
    }
    if (isset($input['media_path'])) {
        $updates[] = "media_path = ?";
        $params[] = $input['media_path'];
    }
    if (isset($input['scheduled_at'])) {
        $updates[] = "scheduled_at = ?";
        $params[] = $input['scheduled_at'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'Nenhuma alteração']);
        exit;
    }

    $sql = "UPDATE agendar_messages SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = $input['id'] ?? 0;

    // Check ownership
    $stmt = $pdo->prepare("SELECT id, media_path FROM agendar_messages WHERE id = ? AND account_id = ?");
    $stmt->execute([$id, $accountId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        http_response_code(404);
        echo json_encode(['error' => 'Mensagem não encontrada']);
        exit;
    }

    // Remove file if exists
    if (!empty($msg['media_path']) && file_exists(__DIR__ . '/../' . $msg['media_path'])) {
        @unlink(__DIR__ . '/../' . $msg['media_path']);
    }

    $stmt = $pdo->prepare("DELETE FROM agendar_messages WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);