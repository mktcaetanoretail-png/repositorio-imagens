CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL      PRIMARY KEY,
    user_id     INTEGER     NULL REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INTEGER      NULL,
    detail      JSONB        NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_action      ON audit_log (action);
CREATE INDEX IF NOT EXISTS idx_audit_entity_type ON audit_log (entity_type);
CREATE INDEX IF NOT EXISTS idx_audit_created_at  ON audit_log (created_at);
