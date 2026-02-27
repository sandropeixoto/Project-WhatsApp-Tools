-- database/auth_setup.sql
-- Tabela de usuários do sistema
CREATE TABLE IF NOT EXISTS system_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserindo o primeiro usuário (master) com a senha "123456"
-- ATENÇÃO: É altamente recomendável alterar a senha após o primeiro acesso
INSERT IGNORE INTO system_users (email, password_hash) VALUES 
('belemonline@gmail.com', '$2y$12$MxK4T0lLLNfxgkWD4AbiBeae93zF9EVCcrhBS0AnbLISv4Dpxmpwu');
