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

// Asset version — set APP_VERSION in Vercel dashboard (e.g. git SHA) to bust
// browser cache on deploy. Falls back to date-based string for local dev.
define('ASSET_VER', env('APP_VERSION', date('Ymd')));

// Bootstrap router
$router = new \App\Core\Router();

// Auth routes
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@doLogin');
$router->get('/logout', 'AuthController@doLogout');

// Brand & Location flow
$router->get('/', 'BrandController@index');
$router->get('/marcas/:slug', 'BrandController@locations');
$router->get('/marcas/:brand_slug/:loc_slug', 'LocationController@photos');
$router->post('/marcas/:brand_slug/:loc_slug/carregar', 'LocationController@upload');
$router->post('/marcas/:brand_slug/:loc_slug/carregar/assinar', 'LocationController@uploadSign');
$router->post('/marcas/:brand_slug/:loc_slug/carregar/confirmar', 'LocationController@uploadConfirm');
$router->post('/foto/:id/eliminar', 'LocationController@delete');
$router->get('/foto/:id', 'GalleryController@show');

// Storage — serve uploaded images
$router->get('/storage/images/:slug/:file', 'StorageController@serve');

// Download
$router->get('/download/:id', 'DownloadController@single');
$router->post('/download/bulk', 'DownloadController@bulk');

// Converter
$router->get('/conversor', 'ConverterController@index');
$router->post('/conversor/processar', 'ConverterController@process');
$router->post('/conversor/estimar', 'ConverterController@estimate');

// Admin — Users
$router->get('/admin/utilizadores', 'AdminController@userList');
$router->get('/admin/utilizadores/criar', 'AdminController@userCreate');
$router->post('/admin/utilizadores/criar', 'AdminController@userStore');
$router->get('/admin/utilizadores/:id/editar', 'AdminController@userEdit');
$router->post('/admin/utilizadores/:id/editar', 'AdminController@userUpdate');
$router->post('/admin/utilizadores/:id/activar', 'AdminController@userToggle');

// Admin — Brands
$router->get('/admin/marcas', 'AdminController@brandList');
$router->get('/admin/marcas/criar', 'AdminController@brandCreate');
$router->post('/admin/marcas/criar', 'AdminController@brandStore');
$router->get('/admin/marcas/:id/editar', 'AdminController@brandEdit');
$router->post('/admin/marcas/:id/editar', 'AdminController@brandUpdate');
$router->post('/admin/marcas/:id/eliminar', 'AdminController@brandDelete');

// Admin — Locations
$router->get('/admin/marcas/:id/localizacoes', 'AdminController@locationList');
$router->get('/admin/marcas/:id/localizacoes/criar', 'AdminController@locationCreate');
$router->post('/admin/marcas/:id/localizacoes/criar', 'AdminController@locationStore');
$router->post('/admin/marcas/:id/localizacoes/:loc_id/eliminar', 'AdminController@locationDelete');

// Admin — Images
$router->post('/admin/imagens/:id/restaurar', 'AdminController@imageRestore');
$router->post('/admin/imagens/:id/eliminar', 'AdminController@imageHardDelete');

$router->dispatch();
