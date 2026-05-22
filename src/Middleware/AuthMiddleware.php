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
        // AUTH DISABLED FOR TESTING — re-enable before production
    }

    public function requirePermission(string $action): void
    {
        // AUTH DISABLED FOR TESTING — re-enable before production
    }
}
