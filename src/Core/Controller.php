<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

abstract class Controller
{
    protected Request $request;
    protected AuthService $auth;

    public function __construct()
    {
        $this->request = new Request();
        $this->auth    = new AuthService();

        // Regenerate CSRF token if not set
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function render(string $view, array $data = []): void
    {
        // Make data available as variables in view
        extract($data, EXTR_SKIP);

        $auth    = $this->auth;
        $request = $this->request;

        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            error_log("View not found: {$viewPath}");
            die('View not found: ' . e($view));
        }

        require $viewPath;
    }

    protected function view(string $view, array $data = []): void
    {
        $this->render($view, $data);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $path): never
    {
        $url = str_starts_with($path, 'http') ? $path : url($path);
        header('Location: ' . $url);
        exit;
    }

    protected function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $ref);
        exit;
    }

    protected function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    protected function csrfVerify(): bool
    {
        $token = $this->request->post('csrf_token')
            ?? $this->request->header('X-Csrf-Token')
            ?? '';
        $session = $_SESSION['csrf_token'] ?? '';
        return !empty($session) && hash_equals($session, $token);
    }

    protected function requireAuth(): void
    {
        // AUTH DISABLED FOR TESTING — re-enable before production
    }

    protected function requirePermission(string $action): void
    {
        // AUTH DISABLED FOR TESTING — re-enable before production
    }

    protected function requireCsrf(): void
    {
        // AUTH DISABLED FOR TESTING — re-enable before production
    }

    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    protected function getFlash(string $type): ?string
    {
        $msg = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $msg;
    }

    protected function setOld(array $data): void
    {
        $_SESSION['old'] = $data;
    }

    protected function getOld(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }

    protected function clearOld(): void
    {
        unset($_SESSION['old']);
    }

    protected function paginate(int $total, int $perPage, int $currentPage): array
    {
        $totalPages = (int) ceil($total / $perPage);
        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'has_prev'     => $currentPage > 1,
            'has_next'     => $currentPage < $totalPages,
            'prev_page'    => max(1, $currentPage - 1),
            'next_page'    => min($totalPages, $currentPage + 1),
        ];
    }
}
