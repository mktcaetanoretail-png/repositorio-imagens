CREATE TABLE IF NOT EXISTS images (
    id                  SERIAL          PRIMARY KEY,
    filename            VARCHAR(255)    NOT NULL,
    original_filename   VARCHAR(255)    NOT NULL,
    filepath            VARCHAR(512)    NOT NULL,
    original_filepath   VARCHAR(512)    NOT NULL,
    thumb_filepath      VARCHAR(512)    NOT NULL,
    filesize            INTEGER         NOT NULL DEFAULT 0,
    original_filesize   INTEGER         NOT NULL DEFAULT 0,
    optimized_filesize  INTEGER         NOT NULL DEFAULT 0,
    optimization_ratio  NUMERIC(5,2)    NOT NULL DEFAULT 0.00,
    width               INTEGER         NOT NULL DEFAULT 0,
    height              INTEGER         NOT NULL DEFAULT 0,
    mime_type           VARCHAR(100)    NOT NULL,
    brand_id            INTEGER         NOT NULL REFERENCES brands(id),
    location_id         INTEGER         NOT NULL REFERENCES locations(id),
    uploaded_by         INTEGER         NOT NULL REFERENCES users(id),
    created_at          TIMESTAMP       NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMP       NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMP       NULL
);

CREATE INDEX IF NOT EXISTS idx_images_brand_id    ON images (brand_id);
CREATE INDEX IF NOT EXISTS idx_images_location_id ON images (location_id);
CREATE INDEX IF NOT EXISTS idx_images_uploaded_by ON images (uploaded_by);
CREATE INDEX IF NOT EXISTS idx_images_deleted_at  ON images (deleted_at);
CREATE INDEX IF NOT EXISTS idx_images_created_at  ON images (created_at);

DROP TRIGGER IF EXISTS trg_images_updated_at ON images;
CREATE TRIGGER trg_images_updated_at
    BEFORE UPDATE ON images
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
