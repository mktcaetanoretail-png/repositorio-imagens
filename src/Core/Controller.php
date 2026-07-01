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
        if ($this->auth->check()) {
            return;
        }

        if ($this->wantsJson()) {
            $this->json(['success' => false, 'error' => 'Sessão expirada. Inicie sessão novamente.'], 401);
        }

        $this->redirect('/login');
    }

    protected function requirePermission(string $action): void
    {
        $this->requireAuth();

        if ($this->auth->can($action)) {
            return;
        }

        if ($this->wantsJson()) {
            $this->json(['success' => false, 'error' => 'Sem permissão para esta acção.'], 403);
        }

        http_response_code(403);
        require __DIR__ . '/../Views/errors/403.php';
        exit;
    }

    protected function requireCsrf(): void
    {
        if ($this->csrfVerify()) {
            return;
        }

        if ($this->wantsJson()) {
            $this->json(['success' => false, 'error' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
        }

        $this->setFlash('error', 'Sessão expirada. Por favor tente novamente.');
        $this->back();
    }

    /**
     * Whether the current request expects a JSON response (AJAX/fetch calls)
     * rather than a full page — used to decide between redirecting and
     * returning a JSON error when auth/CSRF checks fail. A real <form>
     * submission is a top-level navigation, which browsers mark with
     * "Sec-Fetch-Mode: navigate"; fetch() calls never use that value, so
     * this reliably distinguishes AJAX endpoints from page-based forms
     * without needing every fetch() call site to set a custom header.
     */
    protected function wantsJson(): bool
    {
        $fetchMode = $this->request->header('Sec-Fetch-Mode');
        if ($fetchMode !== null && $fetchMode !== '') {
            return $fetchMode !== 'navigate';
        }

        if ($this->request->header('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        $accept = $this->request->header('Accept') ?? '';
        return str_contains($accept, 'application/json');
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
