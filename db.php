<?php
// db.php - Conexão centralizada MySQL

$DB_HOST = '192.185.214.31';
$DB_NAME = 'sspeixot_whatsapp';
$DB_USER = 'sspeixot_whatsapp';
$DB_PASS = 'Senha@2026';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
        );
}
catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Falha na conexão com o banco de dados: ' . $e->getMessage()]));
}