<?php

/**
 * Database Seeder
 * Run: php database/seeds/seed.php
 *
 * Creates:
 *   - 1 admin user (admin@caetano.pt / Admin1234!)
 *   - 4 brands: Toyota, BMW, Mercedes, Seat
 *   - 4 locations (if not already present)
 */

declare(strict_types=1);

// Bootstrap
$rootDir = dirname(__DIR__, 2);

// Check for autoloader
if (!file_exists($rootDir . '/vendor/autoload.php')) {
    die("Error: vendor/autoload.php not found. Run 'composer install' first.\n");
}

require_once $rootDir . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable($rootDir);
$dotenv->load();

// Connect
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'repositorio_imagens';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "Connected to database.\n\n";

// ─── Admin User ───────────────────────────────────────────────────────────────

$adminEmail = 'admin@caetano.pt';
$adminPass  = 'Admin1234!';

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$adminEmail]);
$existing = $stmt->fetch();

if ($existing) {
    echo "Admin user already exists (id={$existing['id']}).\n";
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        'Admin',
        $adminEmail,
        password_hash($adminPass, PASSWORD_BCRYPT),
        'admin',
        1,
    ]);
    $adminId = $pdo->lastInsertId();
    echo "Created admin user: {$adminEmail} / {$adminPass} (id={$adminId})\n";
}

// ─── Brands ───────────────────────────────────────────────────────────────────

$brands = [
    'Toyota'   => 'toyota',
    'BMW'      => 'bmw',
    'Mercedes' => 'mercedes',
    'Seat'     => 'seat',
];

$storageBase = getenv('STORAGE_PATH') ?: $rootDir . '/storage/images';

foreach ($brands as $brandName => $brandSlug) {
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE slug = ? LIMIT 1");
    $stmt->execute([$brandSlug]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Brand '{$brandName}' already exists.\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO brands (name, slug) VALUES (?, ?)");
        $stmt->execute([$brandName, $brandSlug]);
        $brandId = $pdo->lastInsertId();
        echo "Created brand: {$brandName} (slug={$brandSlug}, id={$brandId})\n";

        // Create storage directory
        $dir = rtrim($storageBase, '/') . '/' . $brandSlug;
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "  Created storage directory: {$dir}\n";
            } else {
                echo "  Warning: could not create {$dir}\n";
            }
        }
    }
}

// ─── Locations ────────────────────────────────────────────────────────────────

$locations = [
    ['name' => 'Fachada',          'slug' => 'facade'],
    ['name' => 'Showroom',         'slug' => 'showroom'],
    ['name' => 'Exterior Oficina', 'slug' => 'workshop_exterior'],
    ['name' => 'Interior Oficina', 'slug' => 'workshop_interior'],
];

foreach ($locations as $loc) {
    $stmt = $pdo->prepare("SELECT id FROM locations WHERE slug = ? LIMIT 1");
    $stmt->execute([$loc['slug']]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Location '{$loc['name']}' already exists.\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO locations (name, slug) VALUES (?, ?)");
        $stmt->execute([$loc['name'], $loc['slug']]);
        $locId = $pdo->lastInsertId();
        echo "Created location: {$loc['name']} (id={$locId})\n";
    }
}

echo "\nSeed completed successfully.\n";
echo "\n--- Default credentials ---\n";
echo "Email   : admin@caetano.pt\n";
echo "Password: Admin1234!\n";
echo "URL     : " . (getenv('APP_URL') ?: 'http://localhost') . "/login\n";
