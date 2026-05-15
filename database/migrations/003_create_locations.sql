CREATE TABLE IF NOT EXISTS locations (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO locations (name, slug) VALUES
    ('Fachada',          'facade'),
    ('Showroom',         'showroom'),
    ('Exterior Oficina', 'workshop_exterior'),
    ('Interior Oficina', 'workshop_interior');
