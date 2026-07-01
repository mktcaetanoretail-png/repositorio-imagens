<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Image;
use App\Models\Location;
use App\Models\User;
use App\Services\StorageResolver;
use App\Services\SupabaseStorage;

class AdminController extends Controller
{
    // ─── Users ────────────────────────────────────────────────────────────────

    public function userList(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');

        $userModel = new User();
        $users     = $userModel->findAll([], 'name ASC');

        $this->render('admin/users/index', [
            'users'       => $users,
            'flash_ok'    => $this->getFlash('success'),
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function userCreate(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');

        $this->render('admin/users/form', [
            'user'        => null,
            'action'      => '/admin/utilizadores/criar',
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function userStore(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');
        $this->requireCsrf();

        $data     = $this->validateUserInput($request);
        $errors   = $data['errors'];
        $name     = $data['name'];
        $email    = $data['email'];
        $password = $data['password'];
        $role     = $data['role'];

        if (empty($password)) {
            $errors[] = 'A palavra-passe é obrigatória para novos utilizadores.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $this->setOld(['name' => $name, 'email' => $email, 'role' => $role]);
            $this->redirect('/admin/utilizadores/criar');
        }

        $userModel = new User();

        // Check email unique
        if ($userModel->findByEmail($email)) {
            $this->setFlash('error', 'Já existe um utilizador com este email.');
            $this->setOld(['name' => $name, 'email' => $email, 'role' => $role]);
            $this->redirect('/admin/utilizadores/criar');
        }

        $id = $userModel->create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => $role,
            'active'        => 1,
        ]);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'user_create', 'user', $id, ['email' => $email, 'role' => $role]);

        $this->setFlash('success', 'Utilizador criado com sucesso.');
        $this->redirect('/admin/utilizadores');
    }

    public function userEdit(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');

        $id        = (int) ($params['id'] ?? 0);
        $userModel = new User();
        $user      = $userModel->find($id);

        if (!$user) {
            $this->setFlash('error', 'Utilizador não encontrado.');
            $this->redirect('/admin/utilizadores');
        }

        $this->render('admin/users/form', [
            'user'        => $user,
            'action'      => '/admin/utilizadores/' . $id . '/editar',
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function userUpdate(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');
        $this->requireCsrf();

        $id        = (int) ($params['id'] ?? 0);
        $userModel = new User();
        $user      = $userModel->find($id);

        if (!$user) {
            $this->setFlash('error', 'Utilizador não encontrado.');
            $this->redirect('/admin/utilizadores');
        }

        $data   = $this->validateUserInput($request);
        $errors = $data['errors'];
        $name   = $data['name'];
        $email  = $data['email'];
        $role   = $data['role'];

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $this->redirect('/admin/utilizadores/' . $id . '/editar');
        }

        // Check email unique (excluding self)
        $existing = $userModel->findByEmail($email);
        if ($existing && (int) $existing['id'] !== $id) {
            $this->setFlash('error', 'Já existe outro utilizador com este email.');
            $this->redirect('/admin/utilizadores/' . $id . '/editar');
        }

        $updateData = ['name' => $name, 'email' => $email, 'role' => $role];

        $password = trim($request->post('password', ''));
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $this->setFlash('error', 'A palavra-passe deve ter pelo menos 8 caracteres.');
                $this->redirect('/admin/utilizadores/' . $id . '/editar');
            }
            $updateData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $userModel->update($id, $updateData);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'user_update', 'user', $id, ['email' => $email, 'role' => $role]);

        $this->setFlash('success', 'Utilizador actualizado.');
        $this->redirect('/admin/utilizadores');
    }

    public function userToggle(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_users');
        $this->requireCsrf();

        $id        = (int) ($params['id'] ?? 0);
        $userModel = new User();
        $user      = $userModel->find($id);

        if (!$user) {
            $this->json(['success' => false, 'error' => 'Utilizador não encontrado.'], 404);
        }

        // Prevent admin from deactivating themselves
        $me = $this->auth->user();
        if ($me['id'] === $id) {
            $this->json(['success' => false, 'error' => 'Não pode desactivar a sua própria conta.'], 422);
        }

        $userModel->toggle($id);

        $fresh = $userModel->find($id);
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'user_toggle', 'user', $id, [
            'new_status' => $fresh['active'] ? 'active' : 'inactive',
        ]);

        $this->json(['success' => true, 'active' => (bool) $fresh['active']]);
    }

    // ─── Brands ───────────────────────────────────────────────────────────────

    public function brandList(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $brandModel = new Brand();
        $brands     = $brandModel->findAll([], 'name ASC');

        $this->render('admin/brands/index', [
            'brands'      => $brands,
            'flash_ok'    => $this->getFlash('success'),
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function brandCreate(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $this->render('admin/brands/form', [
            'brand'       => null,
            'action'      => '/admin/marcas/criar',
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function brandStore(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $name = trim($request->post('name', ''));
        $slug = slugify($name);

        if (empty($name)) {
            $this->setFlash('error', 'O nome da marca é obrigatório.');
            $this->redirect('/admin/marcas/criar');
        }

        $brandModel = new Brand();
        if ($brandModel->slugExists($slug)) {
            $this->setFlash('error', 'Já existe uma marca com este nome/slug.');
            $this->redirect('/admin/marcas/criar');
        }

        $id = $brandModel->create(['name' => $name, 'slug' => $slug]);

        // Create storage directory
        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $brandDir    = rtrim($storageBase, '/') . '/' . $slug;
        if (!is_dir($brandDir)) {
            @mkdir($brandDir, 0755, true);
        }

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'brand_create', 'brand', $id, ['name' => $name, 'slug' => $slug]);

        $this->setFlash('success', 'Marca criada com sucesso.');
        $this->redirect('/admin/marcas');
    }

    public function brandEdit(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $id         = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($id);

        if (!$brand) {
            $this->setFlash('error', 'Marca não encontrada.');
            $this->redirect('/admin/marcas');
        }

        $this->render('admin/brands/form', [
            'brand'       => $brand,
            'action'      => '/admin/marcas/' . $id . '/editar',
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function brandUpdate(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $id         = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($id);

        if (!$brand) {
            $this->setFlash('error', 'Marca não encontrada.');
            $this->redirect('/admin/marcas');
        }

        $name = trim($request->post('name', ''));
        $slug = slugify($name);

        if (empty($name)) {
            $this->setFlash('error', 'O nome da marca é obrigatório.');
            $this->redirect('/admin/marcas/' . $id . '/editar');
        }

        if ($brandModel->slugExists($slug, $id)) {
            $this->setFlash('error', 'Já existe outra marca com este nome/slug.');
            $this->redirect('/admin/marcas/' . $id . '/editar');
        }

        $brandModel->update($id, ['name' => $name, 'slug' => $slug]);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'brand_update', 'brand', $id, ['name' => $name, 'slug' => $slug]);

        $this->setFlash('success', 'Marca actualizada.');
        $this->redirect('/admin/marcas');
    }

    public function brandDelete(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $id         = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($id);

        if (!$brand) {
            $this->json(['success' => false, 'error' => 'Marca não encontrada.'], 404);
        }

        // Check if brand has images
        $imageModel = new Image();
        $count      = $imageModel->count(['brand_id' => $id]);

        if ($count > 0) {
            $this->json([
                'success' => false,
                'error'   => "Não é possível apagar esta marca: tem {$count} imagem(ns) associada(s).",
            ], 422);
        }

        $brandModel->hardDelete($id);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'brand_delete', 'brand', $id, ['name' => $brand['name']]);

        $this->json(['success' => true]);
    }

    // ─── Deleted Images ───────────────────────────────────────────────────────

    private const TRASH_RETENTION_DAYS = 30;

    public function trashList(Request $request, array $params = []): void
    {
        $this->requirePermission('restore_images');

        $imageModel = new Image();
        $images     = $imageModel->findDeleted();

        $base = rtrim(env('APP_URL', ''), '/') . '/storage/images/';
        foreach ($images as &$image) {
            $brandSlug          = $image['brand_slug'] ?? slugify($image['brand_name'] ?? '');
            $image['thumb_url'] = StorageResolver::resolveUrl($image['thumb_filepath'] ?? '', $base . $brandSlug);
            $image['is_old']    = strtotime($image['deleted_at']) < strtotime('-' . self::TRASH_RETENTION_DAYS . ' days');
        }
        unset($image);

        $oldCount = count(array_filter($images, fn($img) => $img['is_old']));

        $this->render('admin/trash/index', [
            'images'           => $images,
            'old_count'        => $oldCount,
            'retention_days'   => self::TRASH_RETENTION_DAYS,
            'can_hard_delete'  => $this->auth->can('hard_delete_images'),
            'flash_ok'         => $this->getFlash('success'),
            'flash_error'      => $this->getFlash('error'),
            'csrf_token'       => $this->csrfToken(),
        ]);
    }

    public function imageRestore(Request $request, array $params = []): void
    {
        $this->requirePermission('restore_images');
        $this->requireCsrf();

        $id         = (int) ($params['id'] ?? 0);
        $imageModel = new Image();
        $image      = $imageModel->findWithRelations($id);

        if (!$image) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        if ($conflict = $this->findRestoreConflict($imageModel, $image)) {
            $this->json(['success' => false, 'error' => $conflict], 422);
        }

        $imageModel->restore($id);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'image_restore', 'image', $id, [
            'filename' => $image['original_filename'],
        ]);

        $this->json(['success' => true]);
    }

    public function imageHardDelete(Request $request, array $params = []): void
    {
        $this->requirePermission('hard_delete_images');
        $this->requireCsrf();

        $id         = (int) ($params['id'] ?? 0);
        $imageModel = new Image();
        $image      = $imageModel->findWithRelations($id);

        if (!$image) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        $this->deleteImageFiles($image);
        $imageModel->hardDelete($id);

        $me = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'image_hard_delete', 'image', $id, [
            'filename' => $image['original_filename'],
        ]);

        $this->json(['success' => true]);
    }

    public function imageBulkHardDelete(Request $request, array $params = []): void
    {
        $this->requirePermission('hard_delete_images');
        $this->requireCsrf();

        $ids = $request->post('ids', []);
        if (!is_array($ids) || empty($ids)) {
            $this->json(['success' => false, 'error' => 'Nenhuma imagem seleccionada.'], 422);
        }
        $ids = array_filter(array_map('intval', $ids));

        $imageModel = new Image();
        $me         = $this->auth->user();
        $auditLog   = new AuditLog();
        $deleted    = 0;

        foreach ($ids as $id) {
            $image = $imageModel->findWithRelations($id);

            // Only allow permanently deleting images that are already in the trash.
            if (!$image || empty($image['deleted_at'])) {
                continue;
            }

            $this->deleteImageFiles($image);
            $imageModel->hardDelete($id);

            $auditLog->log($me['id'], 'image_hard_delete', 'image', $id, [
                'filename' => $image['original_filename'],
            ]);

            $deleted++;
        }

        $this->json(['success' => true, 'deleted' => $deleted]);
    }

    public function trashPurgeOld(Request $request, array $params = []): void
    {
        $this->requirePermission('hard_delete_images');
        $this->requireCsrf();

        $imageModel = new Image();
        $images     = $imageModel->findDeletedOlderThan(self::TRASH_RETENTION_DAYS);

        $me       = $this->auth->user();
        $auditLog = new AuditLog();
        $deleted  = 0;

        foreach ($images as $image) {
            $this->deleteImageFiles($image);
            $imageModel->hardDelete((int) $image['id']);

            $auditLog->log($me['id'], 'image_hard_delete', 'image', (int) $image['id'], [
                'filename' => $image['original_filename'],
                'reason'   => 'trash_auto_purge_' . self::TRASH_RETENTION_DAYS . 'd',
            ]);

            $deleted++;
        }

        $this->setFlash('success', "{$deleted} imagem(ns) com mais de " . self::TRASH_RETENTION_DAYS . " dias na lixeira foram eliminadas definitivamente.");
        $this->redirect('/admin/lixeira');
    }

    /**
     * Checks whether restoring $image would collide with an image already
     * occupying its slot, or would exceed the location's photo limit.
     * Returns an error message if restoring should be blocked, null otherwise.
     */
    private function findRestoreConflict(Image $imageModel, array $image): ?string
    {
        $brandId    = (int) $image['brand_id'];
        $locationId = (int) $image['location_id'];
        $slot       = $image['slot'] !== null ? (int) $image['slot'] : null;

        if ($slot !== null) {
            if ($imageModel->findActiveBySlot($brandId, $locationId, $slot)) {
                return 'Já existe uma imagem carregada nesse lugar da localização. Remova-a primeiro para poder restaurar esta.';
            }
            return null;
        }

        if ($imageModel->countByLocation($brandId, $locationId) >= LocationController::MAX_PHOTOS) {
            return 'Esta localização já atingiu o número máximo de fotos. Não é possível restaurar.';
        }

        return null;
    }

    /**
     * Deletes the physical files (local disk and/or Supabase Storage) backing
     * an image record. Used by both single and bulk permanent deletion.
     */
    private function deleteImageFiles(array $image): void
    {
        $fields = ['filepath', 'original_filepath', 'thumb_filepath'];

        foreach ($fields as $field) {
            $path = $image[$field] ?? '';
            if ($path !== '' && !str_starts_with($path, 'http') && file_exists($path)) {
                @unlink($path);
            }
        }

        $storage = new SupabaseStorage();
        if ($storage->isConfigured()) {
            $remotePaths = array_unique(array_filter(
                array_map(fn($f) => $image[$f] ?? '', $fields),
                fn($p) => str_starts_with($p, 'http')
            ));

            if (!empty($remotePaths)) {
                try {
                    $storage->delete(array_map([$storage, 'pathFromUrl'], $remotePaths));
                } catch (\Throwable $e) {
                    error_log('SupabaseStorage::delete failed: ' . $e->getMessage());
                }
            }
        }
    }

    // ─── Location Audit ─────────────────────────────────────────────────────────

    public function locationAudit(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $locationModel = new Location();
        $locations     = $locationModel->findAllWithPhotoCounts();

        $maxPhotos = LocationController::MAX_PHOTOS;
        foreach ($locations as &$location) {
            $location['photo_count'] = (int) $location['photo_count'];
            $location['missing']     = max(0, $maxPhotos - $location['photo_count']);
        }
        unset($location);

        $onlyMissing = $request->get('apenas_incompletas', '1') === '1';
        $filtered    = $onlyMissing
            ? array_values(array_filter($locations, fn($l) => $l['missing'] > 0))
            : $locations;

        $this->render('admin/locations/audit', [
            'locations'    => $filtered,
            'total_count'  => count($locations),
            'missing_count'=> count(array_filter($locations, fn($l) => $l['missing'] > 0)),
            'only_missing' => $onlyMissing,
            'max_photos'   => $maxPhotos,
            'csrf_token'   => $this->csrfToken(),
        ]);
    }

    // ─── Locations ────────────────────────────────────────────────────────────────

    public function locationList(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $brandId    = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($brandId);

        if (!$brand) {
            $this->setFlash('error', 'Marca não encontrada.');
            $this->redirect('/admin/marcas');
        }

        $locationModel = new Location();
        $locations     = $locationModel->findByBrand($brandId);

        $imageModel = new Image();
        $countMap   = $imageModel->countsByBrand($brandId);
        foreach ($locations as &$location) {
            $location['image_count'] = $countMap[(int) $location['id']] ?? 0;
        }
        unset($location);

        $this->render('admin/brands/locations', [
            'brand'       => $brand,
            'locations'   => $locations,
            'flash_ok'    => $this->getFlash('success'),
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function locationCreate(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $brandId    = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($brandId);

        if (!$brand) {
            $this->redirect('/admin/marcas');
        }

        $this->render('admin/brands/location_form', [
            'brand'       => $brand,
            'location'    => null,
            'action'      => '/admin/marcas/' . $brandId . '/localizacoes/criar',
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function locationStore(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $brandId    = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($brandId);

        if (!$brand) {
            $this->redirect('/admin/marcas');
        }

        $name = trim($request->post('name', ''));
        $slug = slugify($name);

        if (empty($name)) {
            $this->setFlash('error', 'O nome da localização é obrigatório.');
            $this->redirect('/admin/marcas/' . $brandId . '/localizacoes/criar');
        }

        $locationModel = new Location();
        if ($locationModel->slugExistsForBrand($slug, $brandId)) {
            $this->setFlash('error', 'Já existe uma localização com este nome para esta marca.');
            $this->redirect('/admin/marcas/' . $brandId . '/localizacoes/criar');
        }

        try {
            $locationId = $locationModel->create([
                'name'     => $name,
                'slug'     => $slug,
                'brand_id' => $brandId,
            ]);
        } catch (\Throwable $e) {
            error_log('locationStore failed: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível criar a localização. Tente um nome diferente.');
            $this->redirect('/admin/marcas/' . $brandId . '/localizacoes/criar');
        }

        $me       = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'location_create', 'location', $locationId, [
            'name'  => $name,
            'brand' => $brand['name'],
        ]);

        $this->setFlash('success', 'Localização criada com sucesso.');
        $this->redirect('/admin/marcas/' . $brandId . '/localizacoes');
    }

    public function locationDelete(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $brandId    = (int) ($params['id'] ?? 0);
        $locationId = (int) ($params['loc_id'] ?? 0);

        $locationModel = new Location();
        $location      = $locationModel->find($locationId);

        if (!$location || (int) ($location['brand_id'] ?? 0) !== $brandId) {
            $this->json(['success' => false, 'error' => 'Localização não encontrada.'], 404);
        }

        $imageModel = new Image();
        $count      = $imageModel->countByLocation($brandId, $locationId);

        if ($count > 0) {
            $this->json([
                'success' => false,
                'error'   => "Não é possível apagar: tem {$count} imagem(ns) associada(s). Elimine primeiro as fotos.",
            ], 422);
        }

        $trashedCount = $imageModel->countTrashedByLocation($brandId, $locationId);

        if ($trashedCount > 0) {
            $this->json([
                'success' => false,
                'error'   => "Não é possível apagar: tem {$trashedCount} imagem(ns) na lixeira associada(s). Elimine-as permanentemente na Lixeira antes de apagar a localização.",
            ], 422);
        }

        $locationModel->hardDelete($locationId);

        $me       = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($me['id'], 'location_delete', 'location', $locationId, [
            'name' => $location['name'],
        ]);

        $this->json(['success' => true]);
    }

    // ─── Bulk Location Import ───────────────────────────────────────────────────

    public function locationImportForm(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');

        $this->render('admin/locations/import', [
            'flash_error' => $this->getFlash('error'),
            'csrf_token'  => $this->csrfToken(),
        ]);
    }

    public function locationImportPreview(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $raw = $request->post('data', '');

        if (trim($raw) === '') {
            $this->setFlash('error', 'Cole a lista de marcas e localizações antes de continuar.');
            $this->redirect('/admin/localizacoes/importar');
        }

        $rows = $this->parseLocationImportRows($raw);

        $this->render('admin/locations/import_preview', [
            'rows'       => $rows,
            'raw'        => $raw,
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function locationImportConfirm(Request $request, array $params = []): void
    {
        $this->requirePermission('manage_brands');
        $this->requireCsrf();

        $raw  = $request->post('data', '');
        $rows = $this->parseLocationImportRows($raw);

        $locationModel = new Location();
        $me            = $this->auth->user();
        $auditLog      = new AuditLog();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($row['status'] !== 'ok') {
                $skipped++;
                continue;
            }

            try {
                $locationId = $locationModel->create([
                    'name'     => $row['location_name'],
                    'slug'     => $row['location_slug'],
                    'brand_id' => $row['brand_id'],
                ]);
            } catch (\Throwable $e) {
                error_log('Bulk location import failed for "' . $row['raw'] . '": ' . $e->getMessage());
                $skipped++;
                continue;
            }

            $auditLog->log($me['id'], 'location_create', 'location', $locationId, [
                'name'  => $row['location_name'],
                'brand' => $row['brand_name'],
            ]);

            $created++;
        }

        $this->setFlash(
            'success',
            "Importação concluída: {$created} localização(ões) criada(s), {$skipped} ignorada(s)."
        );
        $this->redirect('/admin/marcas');
    }

    /**
     * Parses pasted "Marca - Localização" text into rows with a match status.
     * Does not touch the database except for read-only duplicate checks.
     */
    private function parseLocationImportRows(string $raw): array
    {
        $brandModel = new Brand();
        $brands     = $brandModel->findAll([], 'name ASC');

        $brandsByKey = [];
        foreach ($brands as $b) {
            $brandsByKey[mb_strtolower(trim($b['name']))] = $b;
        }

        $locationModel = new Location();
        $lines         = preg_split('/\r\n|\r|\n/', $raw);
        $rows          = [];
        $seen          = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/[\t ]+-[\t ]+/u', $line, 2);
            if (count($parts) !== 2) {
                $rows[] = [
                    'raw'     => $line,
                    'status'  => 'invalid',
                    'message' => 'Formato inválido (esperado "Marca - Localização").',
                ];
                continue;
            }

            [$brandTextRaw, $locationName] = $parts;
            $brandTextRaw = trim($brandTextRaw);
            $locationName = trim($locationName);

            if (mb_strtolower($brandTextRaw) === 'marca') {
                continue; // header row
            }

            $candidates = [mb_strtolower($brandTextRaw)];
            if (preg_match('/^caetano\s+(.+)$/iu', $brandTextRaw, $m)) {
                $candidates[] = mb_strtolower(trim($m[1]));
            }

            $brand = null;
            foreach ($candidates as $candidate) {
                if (isset($brandsByKey[$candidate])) {
                    $brand = $brandsByKey[$candidate];
                    break;
                }
            }

            if (!$brand) {
                $rows[] = [
                    'raw'           => $line,
                    'brand_text'    => $brandTextRaw,
                    'location_text' => $locationName,
                    'status'        => 'no_brand',
                    'message'       => 'Marca não encontrada.',
                ];
                continue;
            }

            if ($locationName === '') {
                $rows[] = [
                    'raw'        => $line,
                    'brand_text' => $brandTextRaw,
                    'brand_name' => $brand['name'],
                    'status'     => 'invalid',
                    'message'    => 'Nome de localização em falta.',
                ];
                continue;
            }

            $slug   = slugify($locationName);
            $dupKey = $brand['id'] . '|' . $slug;

            if (isset($seen[$dupKey]) || $locationModel->slugExistsForBrand($slug, (int) $brand['id'])) {
                $rows[] = [
                    'raw'           => $line,
                    'brand_text'    => $brandTextRaw,
                    'brand_name'    => $brand['name'],
                    'location_text' => $locationName,
                    'status'        => 'duplicate',
                    'message'       => 'Já existe esta localização para a marca.',
                ];
                continue;
            }

            $seen[$dupKey] = true;

            $rows[] = [
                'raw'           => $line,
                'brand_text'    => $brandTextRaw,
                'brand_id'      => (int) $brand['id'],
                'brand_name'    => $brand['name'],
                'location_text' => $locationName,
                'location_name' => $locationName,
                'location_slug' => $slug,
                'status'        => 'ok',
                'message'       => 'Pronto a criar.',
            ];
        }

        return $rows;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateUserInput(Request $request): array
    {
        $name   = trim($request->post('name', ''));
        $email  = trim($request->post('email', ''));
        $role   = $request->post('role', 'viewer');
        $errors = [];

        if (empty($name)) {
            $errors[] = 'O nome é obrigatório.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }

        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            $role     = 'viewer';
            $errors[] = 'Função inválida.';
        }

        return [
            'name'     => $name,
            'email'    => $email,
            'role'     => $role,
            'password' => trim($request->post('password', '')),
            'errors'   => $errors,
        ];
    }
}
