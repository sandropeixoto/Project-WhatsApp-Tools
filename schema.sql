-- Schema MySQL para o Painel WhatsApp Tools
-- Banco: sspeixot_whatsapp

CREATE TABLE IF NOT EXISTS uazapi_instances (
    name VARCHAR(255) NOT NULL PRIMARY KEY,
    token VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uazapi_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    instance_name VARCHAR(255) DEFAULT NULL,
    event_type VARCHAR(100) DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    -- Campos extraídos do JSON para consultas diretas
    message_id VARCHAR(100) DEFAULT NULL,
    chat_jid VARCHAR(100) DEFAULT NULL,
    sender_jid VARCHAR(100) DEFAULT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    is_group TINYINT(1) DEFAULT 0,
    from_me TINYINT(1) DEFAULT 0,
    message_type VARCHAR(50) DEFAULT NULL,
    message_timestamp BIGINT DEFAULT NULL,
    text TEXT DEFAULT NULL,
    file_url VARCHAR(1000) DEFAULT NULL,
    mimetype VARCHAR(100) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    quoted_id VARCHAR(100) DEFAULT NULL,
    group_name VARCHAR(255) DEFAULT NULL,
    chat_name VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_instance_name (instance_name),
    INDEX idx_created_at (created_at),
    INDEX idx_chat_jid (chat_jid),
    INDEX idx_message_type (message_type),
    INDEX idx_message_timestamp (message_timestamp),
    INDEX idx_from_me (from_me)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uazapi_groups (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
