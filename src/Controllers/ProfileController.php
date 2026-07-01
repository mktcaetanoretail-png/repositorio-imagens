<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\SupabaseStorage;

class ProfileController extends Controller
{
    public function edit(Request $request, array $params = []): void
    {
        $this->requireAuth();

        $userModel = new User();
        $user      = $userModel->find((int) $this->auth->user()['id']);

        $this->render('profile/edit', [
            'user'        => $user,
            'flash_ok'    => $this->getFlash('success'),
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function update(Request $request, array $params = []): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id        = (int) $this->auth->user()['id'];
        $userModel = new User();
        $user      = $userModel->find($id);

        if (!$user) {
            $this->setFlash('error', 'Utilizador não encontrado.');
            $this->redirect('/perfil');
        }

        $name  = trim($request->post('name', ''));
        $email = trim($request->post('email', ''));
        $errors = [];

        if (empty($name)) {
            $errors[] = 'O nome é obrigatório.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        } else {
            $existing = $userModel->findByEmail($email);
            if ($existing && (int) $existing['id'] !== $id) {
                $errors[] = 'Já existe outro utilizador com este email.';
            }
        }

        $updateData = [];

        $newPassword = trim($request->post('new_password', ''));
        if ($newPassword !== '') {
            $currentPassword = $request->post('current_password', '');
            if (empty($currentPassword) || !$userModel->verifyPassword($currentPassword, $user['password_hash'])) {
                $errors[] = 'Palavra-passe actual incorrecta.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
            } elseif ($newPassword !== $request->post('new_password_confirm', '')) {
                $errors[] = 'A confirmação da nova palavra-passe não coincide.';
            } else {
                $updateData['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $this->redirect('/perfil');
        }

        $updateData['name']  = $name;
        $updateData['email'] = $email;

        if ($request->post('remove_photo') === '1' && !empty($user['photo_path'])) {
            $this->deleteProfilePhoto($user['photo_path']);
            $updateData['photo_path'] = null;
        }

        $userModel->update($id, $updateData);

        $photoError = $this->processProfilePhotoUpload($request, $id, $userModel);
        if ($photoError) {
            $this->setFlash('error', 'Perfil actualizado, mas a foto falhou: ' . $photoError);
        }

        // Refresh session with the updated data
        $fresh = $userModel->find($id);
        $_SESSION['user'] = [
            'id'         => $fresh['id'],
            'name'       => $fresh['name'],
            'email'      => $fresh['email'],
            'role'       => $fresh['role'],
            'photo_path' => $fresh['photo_path'] ?? null,
        ];

        $auditLog = new AuditLog();
        $auditLog->log($id, 'profile_update', 'user', $id, ['email' => $email]);

        $this->setFlash('success', 'Perfil actualizado com sucesso.');
        $this->redirect('/perfil');
    }

    private function processProfilePhotoUpload(Request $request, int $userId, User $userModel): ?string
    {
        $file = $request->file('photo');
        if (!$file || empty($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'erro no envio do ficheiro.';
        }

        if ($file['size'] > 4 * 1024 * 1024) {
            return 'ficheiro demasiado grande (máximo 4 MB).';
        }

        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $magicBytes   = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/jpg'  => ["\xFF\xD8\xFF"],
            'image/png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'image/gif'  => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
            'image/webp' => ["\x52\x49\x46\x46"],
            'image/bmp'  => ["\x42\x4D"],
        ];

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            return 'tipo de ficheiro não suportado (JPG, PNG, GIF ou WEBP).';
        }

        $handle     = fopen($file['tmp_name'], 'rb');
        $fileHeader = fread($handle, 12);
        fclose($handle);

        $validMagic = false;
        foreach ($magicBytes[$mime] ?? [] as $sig) {
            if (str_starts_with($fileHeader, $sig)) {
                $validMagic = true;
                break;
            }
        }
        if (!$validMagic) {
            return 'o ficheiro não é uma imagem válida.';
        }

        $storage = new SupabaseStorage();
        if (!$storage->isConfigured()) {
            return 'o armazenamento de imagens não está configurado.';
        }

        $ext = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            default      => 'jpg',
        };

        try {
            $url = $storage->upload($file['tmp_name'], 'users/' . $userId . '-' . time() . '.' . $ext, $mime);
        } catch (\Throwable $e) {
            error_log('Profile photo upload failed: ' . $e->getMessage());
            return 'falha ao enviar para o armazenamento.';
        }

        $existing = $userModel->find($userId);
        if (!empty($existing['photo_path'])) {
            $this->deleteProfilePhoto($existing['photo_path']);
        }

        $userModel->update($userId, ['photo_path' => $url]);
        return null;
    }

    private function deleteProfilePhoto(string $photoPath): void
    {
        if (!str_starts_with($photoPath, 'http')) {
            return;
        }
        $storage = new SupabaseStorage();
        if (!$storage->isConfigured()) {
            return;
        }
        try {
            $storage->delete([$storage->pathFromUrl($photoPath)]);
        } catch (\Throwable $e) {
            error_log('Profile photo delete failed: ' . $e->getMessage());
        }
    }
}
