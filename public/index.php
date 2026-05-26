<?php

declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env if present (not available on Vercel — env vars set via dashboard)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Error reporting based on env
if (env('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
$lifetime = (int) env('SESSION_LIFETIME', 7200);
ini_set('session.gc_maxlifetime', (string) $lifetime);
session_set_cookie_params(['lifetime' => $lifetime, 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

session_start();

// Remember-me auto-login
if (empty($_SESSION['user']) && !empty($_COOKIE['remember_token'])) {
    $userModel = new \App\Models\User();
    $user = $userModel->findByRememberToken($_COOKIE['remember_token']);
    if ($user && $user['active']) {
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
    }
}

// Bootstrap router
$router = new \App\Core\Router();

// Auth routes
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@doLogin');
$router->get('/logout', 'AuthController@doLogout');

// Brand & Location flow
$router->get('/', 'BrandController@index');
$router->get('/brand/:id', 'BrandController@locations');
$router->get('/brand/:id/location/:loc_id', 'LocationController@photos');
$router->post('/brand/:id/location/:loc_id/upload', 'LocationController@upload');
$router->post('/brand/:id/location/:loc_id/upload/sign', 'LocationController@uploadSign');
$router->post('/brand/:id/location/:loc_id/upload/confirm', 'LocationController@uploadConfirm');
$router->post('/image/:id/delete', 'LocationController@delete');
$router->get('/image/:id', 'GalleryController@show');

// Storage — serve uploaded images
$router->get('/storage/images/:slug/:file', 'StorageController@serve');

// Download
$router->get('/download/:id', 'DownloadController@single');
$router->post('/download/bulk', 'DownloadController@bulk');

// Converter
$router->get('/converter', 'ConverterController@index');
$router->post('/converter/process', 'ConverterController@process');
$router->post('/converter/estimate', 'ConverterController@estimate');

// Admin — Users
$router->get('/admin/users', 'AdminController@userList');
$router->get('/admin/users/create', 'AdminController@userCreate');
$router->post('/admin/users/create', 'AdminController@userStore');
$router->get('/admin/users/:id/edit', 'AdminController@userEdit');
$router->post('/admin/users/:id/edit', 'AdminController@userUpdate');
$router->post('/admin/users/:id/toggle', 'AdminController@userToggle');

// Admin — Brands
$router->get('/admin/brands', 'AdminController@brandList');
$router->get('/admin/brands/create', 'AdminController@brandCreate');
$router->post('/admin/brands/create', 'AdminController@brandStore');
$router->get('/admin/brands/:id/edit', 'AdminController@brandEdit');
$router->post('/admin/brands/:id/edit', 'AdminController@brandUpdate');
$router->post('/admin/brands/:id/delete', 'AdminController@brandDelete');

// Admin — Locations
$router->get('/admin/brands/:id/locations', 'AdminController@locationList');
$router->get('/admin/brands/:id/locations/create', 'AdminController@locationCreate');
$router->post('/admin/brands/:id/locations/create', 'AdminController@locationStore');
$router->post('/admin/brands/:id/locations/:loc_id/delete', 'AdminController@locationDelete');

// Admin — Images
$router->post('/admin/images/:id/restore', 'AdminController@imageRestore');
$router->post('/admin/images/:id/hard-delete', 'AdminController@imageHardDelete');

$router->dispatch();
