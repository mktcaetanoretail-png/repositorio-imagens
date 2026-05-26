-- Migration 011: Add missing indexes for performance
-- Run in Supabase SQL Editor

-- H-5: Index on locations(brand_id) — used in every brand page query
CREATE INDEX IF NOT EXISTS idx_locations_brand_id
    ON locations (brand_id);

-- H-6: Index on users(remember_token) — used for remember-me auto-login on every request
CREATE INDEX IF NOT EXISTS idx_users_remember_token
    ON users (remember_token)
    WHERE remember_token IS NOT NULL;

-- M-9: Composite index on images(brand_id, location_id) for non-deleted rows
--      Covers countByLocation, findByLocation, countsByBrand, previewsByBrand
CREATE INDEX IF NOT EXISTS idx_images_brand_loc_del
    ON images (brand_id, location_id)
    WHERE deleted_at IS NULL;
