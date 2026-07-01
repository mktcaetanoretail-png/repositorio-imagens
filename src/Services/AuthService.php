<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class AuthService
{
    private const PERMISSIONS = [
        'upload'             => ['admin', 'editor'],
        'download'           => ['admin', 'editor', 'viewer'],
        'delete_any'         => ['admin'],
        'delete_own'         => ['admin', 'editor'],
        'manage_users'       => ['admin'],
        'manage_brands'      => ['admin'],
        'view_images'        => ['admin', 'editor', 'viewer'],
        'convert'            => ['admin', 'editor'],
        'download_original'  => ['admin', 'editor'],
        'restore_images'     => ['admin'],
        'hard_delete_images' => ['admin'],
        'view_admin'         => ['admin'],
    ];

    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function login(string $email, string $password, bool $remember = false): bool
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user || !$user['active']) {
            return false;
        }

        // Check account lock
        if ($this->userModel->isLocked($user)) {
            return false;
        }

        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            $this->userModel->incrementLoginAttempts($user['id']);

            // Lock after 5 failed attempts
            $fresh = $this->userModel->find($user['id']);
            if ($fresh && $fresh['login_attempts'] >= 5) {
                $this->userModel->lockAccount($user['id'], 15);
            }

            return false;
        }

        // Successful login
        $this->userModel->resetLoginAttempts($user['id']);

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'photo_path' => $user['photo_path'] ?? null,
        ];

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $this->userModel->setRememberToken($user['id'], $token);
            $days = (int) env('REMEMBER_ME_DAYS', 30);
            setcookie(
                'remember_token',
                $token,
                time() + ($days * 86400),
                '/',
                '',
                true,  // secure
                true   // httpOnly
            );
        }

        return true;
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user) {
            $this->userModel->clearRememberToken($user['id']);
        }

        // Destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        // Clear remember cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return !empty($_SESSION['user']);
    }

    public function can(string $action): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $allowedRoles = self::PERMISSIONS[$action] ?? [];
        return in_array($user['role'], $allowedRoles, true);
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && $user['role'] === 'admin';
    }

    public function generateCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(string $token): bool
    {
        $session = $_SESSION['csrf_token'] ?? '';
        return !empty($session) && hash_equals($session, $token);
    }

    /**
     * Check if current user is locked (for display purposes)
     */
    public function isLocked(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $dbUser = $this->userModel->find($user['id']);
        return $dbUser ? $this->userModel->isLocked($dbUser) : false;
    }
}
