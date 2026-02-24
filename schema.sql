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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_instance_name (instance_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
