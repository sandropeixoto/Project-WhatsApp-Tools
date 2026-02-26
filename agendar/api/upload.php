<?php
// agendar/api/upload.php
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado ou erro no upload']);
    exit;
}

$file = $_FILES['file'];
$baseStorageDir = __DIR__ . '/../storage';
$accountDir = $baseStorageDir . '/account_' . $accountId;

// Create folders if they don't exist
if (!is_dir($baseStorageDir)) {
    mkdir($baseStorageDir, 0777, true);
}
if (!is_dir($accountDir)) {
    mkdir($accountDir, 0777, true);
}

// Generate safe unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($ext))
    $ext = 'bin';

$safeName = md5(uniqid(rand(), true)) . '.' . strtolower($ext);
$localPath = $accountDir . '/' . $safeName;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $localPath)) {
    // Return relative path from the `agendar/` root
    $relativePath = 'storage/account_' . $accountId . '/' . $safeName;
    echo json_encode(['success' => true, 'path' => $relativePath]);
}
else {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao processar o arquivo no servidor']);
}