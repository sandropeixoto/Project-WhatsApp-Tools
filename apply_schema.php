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

// Criar tabela uazapi_groups
$pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_groups (
    jid VARCHAR(100) NOT NULL,
    instance_name VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    owner_jid VARCHAR(100) DEFAULT NULL,
    participant_count INT DEFAULT 0,
    is_announce TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    is_admin TINYINT(1) DEFAULT 0,
    invite_link VARCHAR(500) DEFAULT NULL,
    image_preview TEXT DEFAULT NULL,
    group_created DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (jid, instance_name),
    INDEX idx_instance (instance_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Tabela uazapi_groups criada.\n";

echo "\n🎉 Schema aplicado com sucesso!\n";