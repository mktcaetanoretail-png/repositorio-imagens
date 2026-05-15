<?php

/**
 * Database Seeder — PostgreSQL (Supabase) + MySQL/MariaDB compatible
 * Run: php database/seeds/seed.php
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);

if (!file_exists($rootDir . '/vendor/autoload.php')) {
    die("Error: run 'composer install' first.\n");
}

require_once $rootDir . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($rootDir);
$dotenv->load();

$driver  = $_ENV['DB_DRIVER']  ?? 'pgsql';
$host    = $_ENV['DB_HOST']    ?? 'localhost';
$port    = $_ENV['DB_PORT']    ?? ($driver === 'mysql' ? '3306' : '5432');
$name    = $_ENV['DB_NAME']    ?? 'postgres';
$user    = $_ENV['DB_USER']    ?? 'postgres';
$pass    = $_ENV['DB_PASS']    ?? '';
$sslmode = $_ENV['DB_SSLMODE'] ?? 'require';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if ($driver === 'mysql') {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] =
            "SET NAMES utf8mb4, sql_mode = CONCAT(@@sql_mode, ',ANSI_QUOTES')";
    } else {
        $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
    }
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

echo "Connected ({$driver}).\n\n";

// Helper: insert returning id (works for both pgsql and mysql)
function insertReturningId(PDO $pdo, string $driver, string $sql, array $params): int
{
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare($sql . ' RETURNING id');
        $stmt->execute($params);
        return (int) ($stmt->fetch()['id'] ?? 0);
    }
    $pdo->prepare($sql)->execute($params);
    return (int) $pdo->lastInsertId();
}

// ─── Admin User ───────────────────────────────────────────────────────────────

$adminEmail = 'admin@caetano.pt';
$adminPass  = 'Admin1234!';

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$adminEmail]);
$existing = $stmt->fetch();

if ($existing) {
    echo "Admin already exists (id={$existing['id']}).\n";
} else {
    $id = insertReturningId($pdo, $driver,
        'INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?)',
        ['Admin', $adminEmail, password_hash($adminPass, PASSWORD_BCRYPT), 'admin', true]
    );
    echo "Created admin: {$adminEmail} / {$adminPass} (id={$id})\n";
}

// ─── Brands ───────────────────────────────────────────────────────────────────

$brands = [
    'Toyota'   => 'toyota',
    'BMW'      => 'bmw',
    'Mercedes' => 'mercedes',
    'Seat'     => 'seat',
];

$storageBase = $_ENV['STORAGE_PATH'] ?? $rootDir . '/storage/images';

foreach ($brands as $brandName => $brandSlug) {
    $stmt = $pdo->prepare('SELECT id FROM brands WHERE slug = ? LIMIT 1');
    $stmt->execute([$brandSlug]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Brand '{$brandName}' already exists.\n";
        continue;
    }

    $id = insertReturningId($pdo, $driver,
        'INSERT INTO brands (name, slug) VALUES (?, ?)',
        [$brandName, $brandSlug]
    );
    echo "Created brand: {$brandName} (id={$id})\n";

    $dir = rtrim($storageBase, '/') . '/' . $brandSlug;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        echo "  Warning: could not create {$dir}\n";
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
    $stmt = $pdo->prepare('SELECT id FROM locations WHERE slug = ? LIMIT 1');
    $stmt->execute([$loc['slug']]);
    if ($stmt->fetch()) {
        echo "Location '{$loc['name']}' already exists.\n";
        continue;
    }
    $id = insertReturningId($pdo, $driver,
        'INSERT INTO locations (name, slug) VALUES (?, ?)',
        [$loc['name'], $loc['slug']]
    );
    echo "Created location: {$loc['name']} (id={$id})\n";
}

echo "\nSeed completo.\n";
echo "Email   : admin@caetano.pt\n";
echo "Password: Admin1234!\n";
echo "URL     : " . ($_ENV['APP_URL'] ?? 'http://localhost') . "/login\n";
