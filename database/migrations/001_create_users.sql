CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)   NOT NULL,
    email          VARCHAR(150)   NOT NULL UNIQUE,
    password_hash  VARCHAR(255)   NOT NULL,
    role           ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
    active         TINYINT(1)     NOT NULL DEFAULT 1,
    remember_token VARCHAR(64)    NULL,
    login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until   DATETIME       NULL,
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
