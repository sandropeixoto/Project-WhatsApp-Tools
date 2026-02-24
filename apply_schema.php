<?php
// apply_schema.php - Script temporário para criar as tabelas no MySQL remoto
// Execute: php apply_schema.php

require_once __DIR__ . '/db.php';

echo "Conectado ao MySQL!\n";

// Criar tabela uazapi_instances
$pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_instances (
    name VARCHAR(255) NOT NULL PRIMARY KEY,
    token VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Tabela uazapi_instances criada.\n";

// Criar tabela uazapi_logs
$pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    instance_name VARCHAR(255) DEFAULT NULL,
    event_type VARCHAR(100) DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_instance_name (instance_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Tabela uazapi_logs criada.\n";

echo "\n🎉 Schema aplicado com sucesso!\n";