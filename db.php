<?php
// db.php - Conexão centralizada MySQL

// Definir fuso horário padrão solicitado: Americas/Belem (-03)
date_default_timezone_set('America/Belem');

$DB_HOST = 'srv24.prodns.com.br';
$DB_NAME = 'sspeixot_whatsapp';
$DB_USER = 'sspeixot_whatsapp';
$DB_PASS = 'Senh@2026';

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