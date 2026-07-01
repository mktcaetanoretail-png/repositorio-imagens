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

// Safety net for anything not caught by Router::handle() (e.g. errors during
// session handling or controller construction, which run before routing).
// Without this, an uncaught error here produces no valid HTTP response at
// all — the browser sees a dropped connection and reports a generic
// "Failed to fetch" instead of a real error message.
set_exception_handler(function (\Throwable $e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || str_contains($accept, 'application/json')) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Erro interno do servidor.']);
            return;
        }
    }
    $viewFile = __DIR__ . '/../src/Views/errors/500.php';
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        echo 'Internal Server Error';
    }
});

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
$lifetime = (int) env('SESSION_LIFETIME', 7200);
ini_set('session.gc_maxlifetime', (string) $lifetime);
session_set_cookie_params(['lifetime' => $lifetime, 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

// Store sessions in the database — Vercel's serverless instances don't
// share a filesystem, so file-based sessions randomly "disappear" when a
// request lands on a different instance than the one before it.
session_set_save_handler(new \App\Core\DbSessionHandler($lifetime), true);
session_start();

// Remember-me auto-login
if (empty($_SESSION['user']) && !empty($_COOKIE['remember_token'])) {
    $userModel = new \App\Models\User();
    $user = $userModel->findByRememberToken($_COOKIE['remember_token']);
    if ($user && $user['active']) {
        $_SESSION['user'] = [
            'id'         => (int) $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'photo_path' => $user['photo_path'] ?? null,
        ];
    }
}

// Asset version — set APP_VERSION in Vercel dashboard (e.g. git SHA) to bust
// browser cache on deploy. Falls back to 'dev' locally.
define('ASSET_VER', env('APP_VERSION', 'dev'));

// Bootstrap router
$router = new \App\Core\Router();

// Auth routes
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@doLogin');
$router->get('/logout', 'AuthController@doLogout');

// Profile (self-service)
$router->get('/perfil', 'ProfileController@edit');
$router->post('/perfil', 'ProfileController@update');

// Brand & Location flow
$router->get('/', 'BrandController@index');
$router->get('/marcas/:slug', 'BrandController@locations');
$router->get('/marcas/:brand_slug/:loc_slug', 'LocationController@photos');
$router->post('/marcas/:brand_slug/:loc_slug/carregar', 'LocationController@upload');
$router->post('/marcas/:brand_slug/:loc_slug/carregar/assinar', 'LocationController@uploadSign');
$router->post('/marcas/:brand_slug/:loc_slug/carregar/confirmar', 'LocationController@uploadConfirm');
$router->post('/foto/:id/eliminar', 'LocationController@delete');
$router->post('/foto/:id/data', 'LocationController@updateCapturedDate');
$router->get('/foto/:id', 'GalleryController@show');

// Search autocomplete
$router->get('/pesquisa/sugestoes', 'SearchController@suggest');

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
$router->post('/admin/utilizadores/:id/eliminar', 'AdminController@userDelete');

// Admin — Brands
$router->get('/admin/marcas', 'AdminController@brandList');
$router->get('/admin/marcas/criar', 'AdminController@brandCreate');
$router->post('/admin/marcas/criar', 'AdminController@brandStore');
$router->get('/admin/marcas/:id/editar', 'AdminController@brandEdit');
$router->post('/admin/marcas/:id/editar', 'AdminController@brandUpdate');
$router->post('/admin/marcas/:id/eliminar', 'AdminController@brandDelete');

// Admin — Location Audit
$router->get('/admin/localizacoes/auditoria', 'AdminController@locationAudit');

// Admin — Locations
$router->get('/admin/marcas/:id/localizacoes', 'AdminController@locationList');
$router->get('/admin/marcas/:id/localizacoes/criar', 'AdminController@locationCreate');
$router->post('/admin/marcas/:id/localizacoes/criar', 'AdminController@locationStore');
$router->post('/admin/marcas/:id/localizacoes/:loc_id/eliminar', 'AdminController@locationDelete');

// Admin — Bulk Location Import
$router->get('/admin/localizacoes/importar', 'AdminController@locationImportForm');
$router->post('/admin/localizacoes/importar', 'AdminController@locationImportPreview');
$router->post('/admin/localizacoes/importar/confirmar', 'AdminController@locationImportConfirm');

// Admin — Images
$router->get('/admin/lixeira', 'AdminController@trashList');
$router->post('/admin/lixeira/purgar-antigas', 'AdminController@trashPurgeOld');
$router->post('/admin/imagens/eliminar-em-massa', 'AdminController@imageBulkHardDelete');
$router->post('/admin/imagens/:id/restaurar', 'AdminController@imageRestore');
$router->post('/admin/imagens/:id/eliminar', 'AdminController@imageHardDelete');

$router->dispatch();
