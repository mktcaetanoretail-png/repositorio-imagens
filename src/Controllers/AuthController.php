<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function showLogin(Request $request, array $params = []): void
    {
        // Already logged in → redirect to gallery
        if ($this->auth->check()) {
            $this->redirect('/');
        }

        $this->render('auth/login', [
            'error'      => $this->getFlash('error'),
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function doLogin(Request $request, array $params = []): void
    {
        // CSRF check
        $this->requireCsrf();

        $email    = trim($request->post('email', ''));
        $password = $request->post('password', '');
        $remember = (bool) $request->post('remember_me', false);

        if (empty($email) || empty($password)) {
            $this->setFlash('error', 'Email e palavra-passe são obrigatórios.');
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

        $success = $this->auth->login($email, $password, $remember);

        if (!$success) {
            $auditLog = new AuditLog();
            $auditLog->log(null, 'login_failed', 'auth', null, [
                'email' => $email,
                'ip'    => $request->ip(),
            ]);

            $this->setFlash('error', 'Email ou palavra-passe incorrectos. A conta pode estar bloqueada após múltiplas tentativas falhadas.');
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

        $user     = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'login', 'auth', $user['id'], [
            'ip' => $request->ip(),
        ]);

        $this->redirect('/');
    }

    public function doLogout(Request $request, array $params = []): void
    {
        $user = $this->auth->user();

        if ($user) {
            $auditLog = new AuditLog();
            $auditLog->log($user['id'], 'logout', 'auth', $user['id'], []);
        }

        $this->auth->logout();
        $this->redirect('/login');
    }
}
