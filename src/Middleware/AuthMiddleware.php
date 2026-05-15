<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function handle(): void
    {
        if (!$this->auth->check()) {
            // Check for remember token
            if (!empty($_COOKIE['remember_token'])) {
                $userModel = new \App\Models\User();
                $user      = $userModel->findByRememberToken($_COOKIE['remember_token']);
                if ($user && $user['active']) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id'    => $user['id'],
                        'name'  => $user['name'],
                        'email' => $user['email'],
                        'role'  => $user['role'],
                    ];
                    return;
                }
            }

            // Determine if this is an AJAX/JSON request
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                || (($_GET['format'] ?? '') === 'json');

            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Não autenticado.', 'redirect' => '/login']);
                exit;
            }

            header('Location: /login');
            exit;
        }
    }

    public function requirePermission(string $action): void
    {
        $this->handle();

        if (!$this->auth->can($action)) {
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                || (($_GET['format'] ?? '') === 'json');

            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Sem permissão para esta acção.']);
                exit;
            }

            http_response_code(403);
            echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>403 — Acesso Negado</title>';
            echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8f9fa;}';
            echo '.box{text-align:center;padding:2rem;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}';
            echo 'h1{color:#e94560;margin-bottom:.5rem;}a{color:#1a1a2e;}</style></head>';
            echo '<body><div class="box"><h1>403</h1><p>Sem permissão para aceder a esta página.</p>';
            echo '<p><a href="/">Voltar ao início</a></p></div></body></html>';
            exit;
        }
    }
}
