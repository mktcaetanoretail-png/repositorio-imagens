<?php

declare(strict_types=1);

namespace App\Middleware;

class CsrfMiddleware
{
    /**
     * Verify CSRF token from POST data or X-Csrf-Token header.
     * Terminates execution with 419 if token is invalid.
     */
    public function verify(): void
    {
        $token   = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        $session = $_SESSION['csrf_token'] ?? null;

        if (empty($session) || empty($token) || !hash_equals($session, $token)) {
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                || (($_GET['format'] ?? '') === 'json');

            if ($isAjax) {
                http_response_code(419);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Token CSRF inválido. Recarregue a página e tente novamente.']);
                exit;
            }

            http_response_code(419);
            echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Token Inválido</title></head>';
            echo '<body><h1>Token CSRF inválido</h1><p>Por favor, recarregue a página e tente novamente.</p>';
            echo '<p><a href="javascript:history.back()">Voltar</a></p></body></html>';
            exit;
        }
    }

    /**
     * Regenerate CSRF token (call after successful form submission if needed).
     */
    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Get the current CSRF token, generating one if it doesn't exist.
     */
    public function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $this->regenerate();
        }
        return $_SESSION['csrf_token'];
    }
}
