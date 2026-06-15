CREATE TABLE IF NOT EXISTS telegram_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(64) NOT NULL,
    code CHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tv_chat_code (chat_id, code),
    INDEX idx_tv_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
