-- Schema MySQL para o Módulo Agendar
-- Banco: sspeixot_whatsapp

CREATE TABLE IF NOT EXISTS agendar_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    instance_name VARCHAR(255) DEFAULT NULL,
    target_jid VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendar_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    role VARCHAR(20) DEFAULT 'guest',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES agendar_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendar_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(50) NOT NULL,
    token VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendar_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES agendar_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendar_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    user_id INT NOT NULL,
    media_type VARCHAR(20) DEFAULT 'text',
    text TEXT DEFAULT NULL,
    media_path VARCHAR(1000) DEFAULT NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    error_message TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES agendar_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES agendar_users(id) ON DELETE CASCADE,
    INDEX idx_agendar_status_date (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
