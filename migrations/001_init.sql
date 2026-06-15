CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_number VARCHAR(32) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    notification_channel VARCHAR(16) NOT NULL,
    telegram_chat_id VARCHAR(64) NULL,
    phone_number VARCHAR(32) NULL,
    push_endpoint VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ring_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    house_number VARCHAR(32) NOT NULL,
    resident_id INT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    notify_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at TIMESTAMP NULL,
    INDEX idx_ring_house_number (house_number),
    INDEX idx_ring_resident (resident_id),
    CONSTRAINT fk_ring_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS open_links (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ring_event_id BIGINT NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_link_event FOREIGN KEY (ring_event_id) REFERENCES ring_events(id) ON DELETE CASCADE,
    INDEX idx_open_links_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS open_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ring_event_id BIGINT NOT NULL,
    command_token CHAR(64) NOT NULL UNIQUE,
    pulse_ms INT NOT NULL DEFAULT 1200,
    status VARCHAR(16) NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    acked_at TIMESTAMP NULL,
    CONSTRAINT fk_cmd_event FOREIGN KEY (ring_event_id) REFERENCES ring_events(id) ON DELETE CASCADE,
    INDEX idx_cmd_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
