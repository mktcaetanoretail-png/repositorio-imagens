CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NULL,
    action      VARCHAR(100)  NOT NULL,
    entity_type VARCHAR(50)   NOT NULL,
    entity_id   INT UNSIGNED  NULL,
    detail      JSON          NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action      (action),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
