<?php
// apply_schema.php — EXECUTAR VIA BROWSER PARA CRIAR/ATUALIZAR TABELAS
// Deletar este arquivo após o uso!

require_once __DIR__ . '/db.php';

echo "<pre>\n";
echo "=== Aplicando Schema MySQL ===\n\n";

try {
    // Criar tabela uazapi_instances
    $pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_instances (
        name VARCHAR(255) NOT NULL PRIMARY KEY,
        token VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela uazapi_instances OK.\n";

    // Adicionar colunas de perfil em uazapi_instances (idempotente)
    $instanceCols = [
        'status' => "VARCHAR(20) DEFAULT 'disconnected'",
        'profile_name' => 'VARCHAR(255) DEFAULT NULL',
        'profile_pic_url' => 'TEXT DEFAULT NULL',
        'phone_number' => 'VARCHAR(50) DEFAULT NULL',
        'is_business' => 'TINYINT(1) DEFAULT 0',
        'platform' => 'VARCHAR(50) DEFAULT NULL',
    ];
    foreach ($instanceCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE uazapi_instances ADD COLUMN {$col} {$def}");
            echo "  ✅ Coluna instances.{$col} adicionada.\n";
        }
        catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "  ⏭️  Coluna instances.{$col} já existe.\n";
            }
            else {
                echo "  ❌ Erro em instances.{$col}: " . $e->getMessage() . "\n";
            }
        }
    }

    // Criar tabela uazapi_logs (versão básica)
    $pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        instance_name VARCHAR(255) DEFAULT NULL,
        event_type VARCHAR(100) DEFAULT NULL,
        payload LONGTEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_instance_name (instance_name),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela uazapi_logs OK.\n";

    // Adicionar colunas extraídas (idempotente via IF NOT EXISTS workaround)
    $newColumns = [
        'message_id' => 'VARCHAR(100) DEFAULT NULL',
        'chat_jid' => 'VARCHAR(100) DEFAULT NULL',
        'sender_jid' => 'VARCHAR(100) DEFAULT NULL',
        'sender_name' => 'VARCHAR(255) DEFAULT NULL',
        'is_group' => 'TINYINT(1) DEFAULT 0',
        'from_me' => 'TINYINT(1) DEFAULT 0',
        'message_type' => 'VARCHAR(50) DEFAULT NULL',
        'message_timestamp' => 'BIGINT DEFAULT NULL',
        'text' => 'TEXT DEFAULT NULL',
        'file_url' => 'VARCHAR(1000) DEFAULT NULL',
        'mimetype' => 'VARCHAR(100) DEFAULT NULL',
        'file_name' => 'VARCHAR(255) DEFAULT NULL',
        'quoted_id' => 'VARCHAR(100) DEFAULT NULL',
        'group_name' => 'VARCHAR(255) DEFAULT NULL',
        'chat_name' => 'VARCHAR(255) DEFAULT NULL',
    ];

    foreach ($newColumns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE uazapi_logs ADD COLUMN {$col} {$def}");
            echo "  ✅ Coluna {$col} adicionada.\n";
        }
        catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "  ⏭️  Coluna {$col} já existe.\n";
            }
            else {
                echo "  ❌ Erro em {$col}: " . $e->getMessage() . "\n";
            }
        }
    }

    // Adicionar índices (idempotente)
    $indexes = [
        'idx_chat_jid' => 'chat_jid',
        'idx_message_type' => 'message_type',
        'idx_message_timestamp' => 'message_timestamp',
        'idx_from_me' => 'from_me',
    ];

    foreach ($indexes as $idxName => $col) {
        try {
            $pdo->exec("ALTER TABLE uazapi_logs ADD INDEX {$idxName} ({$col})");
            echo "  ✅ Índice {$idxName} criado.\n";
        }
        catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate key name')) {
                echo "  ⏭️  Índice {$idxName} já existe.\n";
            }
            else {
                echo "  ❌ Erro em {$idxName}: " . $e->getMessage() . "\n";
            }
        }
    }

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
    echo "✅ Tabela uazapi_groups OK.\n";

    // Criar tabela uazapi_schedule
    $pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_schedule (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        instance_name VARCHAR(255) NOT NULL,
        task_type VARCHAR(50) NOT NULL DEFAULT 'status',
        payload TEXT NOT NULL,
        scheduled_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        result TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        executed_at DATETIME DEFAULT NULL,
        INDEX idx_schedule_status (status),
        INDEX idx_schedule_at (scheduled_at),
        INDEX idx_schedule_instance (instance_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela uazapi_schedule OK.\n";

    // Criar tabela uazapi_agents
    $pdo->exec("CREATE TABLE IF NOT EXISTS uazapi_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        instance_name VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        prompt TEXT NOT NULL,
        recipient VARCHAR(100) NOT NULL,
        interval_minutes INT NOT NULL DEFAULT 60,
        restricted_hours VARCHAR(100) DEFAULT NULL,
        requires_review TINYINT(1) DEFAULT 1,
        status VARCHAR(20) DEFAULT 'active',
        last_exec_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agent_instance (instance_name),
        INDEX idx_agent_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela uazapi_agents OK.\n";

    echo "\n🎉 Schema aplicado com sucesso!\n";

}
catch (PDOException $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>