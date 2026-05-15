CREATE TABLE IF NOT EXISTS brands (
    id         SERIAL      PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_brands_slug ON brands (slug);
