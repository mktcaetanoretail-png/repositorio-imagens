CREATE TABLE IF NOT EXISTS locations (
    id   SERIAL      PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO locations (name, slug) VALUES
    ('Fachada',          'facade'),
    ('Showroom',         'showroom'),
    ('Exterior Oficina', 'workshop_exterior'),
    ('Interior Oficina', 'workshop_interior')
ON CONFLICT (slug) DO NOTHING;
