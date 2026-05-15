-- Compatible with PostgreSQL (Supabase) and MariaDB/MySQL
-- PostgreSQL version

CREATE TABLE IF NOT EXISTS users (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(100)   NOT NULL,
    email          VARCHAR(150)   NOT NULL UNIQUE,
    password_hash  VARCHAR(255)   NOT NULL,
    role           VARCHAR(20)    NOT NULL DEFAULT 'viewer'
                       CHECK (role IN ('admin', 'editor', 'viewer')),
    active         BOOLEAN        NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(64)    NULL,
    login_attempts SMALLINT       NOT NULL DEFAULT 0,
    locked_until   TIMESTAMP      NULL,
    created_at     TIMESTAMP      NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMP      NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);

-- Trigger to auto-update updated_at on row change
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
