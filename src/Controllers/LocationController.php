<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Image;
use App\Models\Location;
use App\Services\ImageService;
use App\Services\SupabaseStorage;

class LocationController extends Controller
{
    private const MAX_PHOTOS   = 4;
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/jpg', 'image/png',
        'image/gif',  'image/webp', 'image/bmp',
    ];

    public function photos(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        [$brand, $location] = $this->loadBrandLocation(
            (int) ($params['id']      ?? 0),
            (int) ($params['loc_id']  ?? 0)
        );

        $imageModel = new Image();
        $rawImages  = $imageModel->findByLocation($brand['id'], $location['id']);
        $base       = rtrim(env('APP_URL', ''), '/') . '/storage/images/' . $brand['slug'];

        // Build slot map: slot number (1-4) → image row
        $slotMap = [];
        foreach ($rawImages as $img) {
            $img['thumb_url']     = $this->resolveUrl($img['thumb_filepath']    ?? '', $base);
            $img['optimized_url'] = $this->resolveUrl($img['filepath']          ?? '', $base);
            $img['original_url']  = $this->resolveUrl($img['original_filepath'] ?? '', $base);
            $img['download_url']  = '/download/' . $img['id'];
            $img['filesize_human']= formatBytes((int) ($img['filesize'] ?? 0));

            $s = (int) ($img['slot'] ?? 0);
            if ($s >= 1 && $s <= self::MAX_PHOTOS && !isset($slotMap[$s])) {
                $slotMap[$s] = $img;
            } else {
                // Legacy image without slot — put in first free slot
                for ($n = 1; $n <= self::MAX_PHOTOS; $n++) {
                    if (!isset($slotMap[$n])) { $slotMap[$n] = $img; break; }
                }
            }
        }
        $images = $slotMap;

        // Load all brand locations for the sidebar
        $locationModel  = new Location();
        $brandLocations = $locationModel->findByBrand($brand['id']);
        foreach ($brandLocations as &$loc) {
            $loc['image_count'] = $imageModel->countByLocation($brand['id'], $loc['id']);
        }
        unset($loc);

        $storage          = new SupabaseStorage();
        $useDirectUpload  = false; // Browser→Supabase direct upload blocked by CORS on free plan
        $locBase          = '/brand/' . $brand['id'] . '/location/' . $location['id'];

        $this->render('locations/photos', [
            'brand'              => $brand,
            'location'           => $location,
            'images'             => $images,
            'brandLocations'     => $brandLocations,
            'max_photos'         => self::MAX_PHOTOS,
            'slots_available'    => max(0, self::MAX_PHOTOS - count($slotMap)),
            'pageTitle'          => $location['name'] . ' — ' . $brand['name'],
            'flash_ok'           => $this->getFlash('success'),
            'flash_error'        => $this->getFlash('error'),
            'csrf_token'         => $this->csrfToken(),
            'use_direct_upload'  => $useDirectUpload,
            'upload_sign_url'    => url($locBase . '/upload/sign'),
            'upload_confirm_url' => url($locBase . '/upload/confirm'),
        ]);
    }

    public function upload(Request $request, array $params = []): void
    {
        $this->requirePermission('upload');
        $this->requireCsrf();

        [$brand, $location] = $this->loadBrandLocation(
            (int) ($params['id']      ?? 0),
            (int) ($params['loc_id']  ?? 0)
        );

        $imageModel   = new Image();
        $currentCount = $imageModel->countByLocation($brand['id'], $location['id']);

        if ($currentCount >= self::MAX_PHOTOS) {
            $this->json([
                'success' => false,
                'error'   => 'Limite de ' . self::MAX_PHOTOS . ' fotos por localização atingido.',
            ], 422);
        }

        $file = $request->file('image');
        if (!$file || empty($file['tmp_name'])) {
            $this->json(['success' => false, 'error' => 'Nenhum ficheiro recebido.'], 422);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => $this->uploadError($file['error'])], 422);
        }

        $maxBytes = ((int) env('UPLOAD_MAX_SIZE_MB', 4)) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $this->json(['success' => false, 'error' => 'Ficheiro demasiado grande. Máximo: ' . env('UPLOAD_MAX_SIZE_MB', 4) . ' MB.'], 422);
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            $this->json(['success' => false, 'error' => 'Tipo de ficheiro não suportado. Permitido: JPG, PNG, WEBP, GIF.'], 422);
        }

        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $destDir     = rtrim($storageBase, '/') . '/' . $brand['slug'];
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            $this->json(['success' => false, 'error' => 'Erro ao criar pasta de destino.'], 500);
        }

        try {
            $svc    = new ImageService();
            $result = $svc->optimize($file['tmp_name'], $destDir);
        } catch (\Throwable $e) {
            error_log('ImageService::optimize failed: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao processar imagem: ' . $e->getMessage()], 500);
        }

        $user    = $this->auth->user();
        $userId  = (!empty($user['id']) && $user['id'] > 0) ? $user['id'] : null;

        // Upload to Supabase Storage if configured; otherwise keep local paths
        $storage = new SupabaseStorage();
        if ($storage->isConfigured()) {
            try {
                $slug        = $brand['slug'];
                $storedThumb = $storage->upload(
                    $result['thumb_path'],
                    $slug . '/' . basename($result['thumb_path']),
                    'image/jpeg'
                );
                $storedOptimized = $storage->upload(
                    $result['optimized_path'],
                    $slug . '/' . basename($result['optimized_path']),
                    $result['mime_type']
                );
                $storedOriginal = $storage->upload(
                    $result['original_path'],
                    $slug . '/' . basename($result['original_path']),
                    $result['mime_type']
                );
            } catch (\Throwable $e) {
                error_log('SupabaseStorage::upload failed: ' . $e->getMessage());
                $this->json(['success' => false, 'error' => 'Erro ao guardar imagem: ' . $e->getMessage()], 500);
            }
        } else {
            $storedThumb     = $result['thumb_path'];
            $storedOptimized = $result['optimized_path'];
            $storedOriginal  = $result['original_path'];
        }

        $slot = max(1, min(self::MAX_PHOTOS, (int) $request->post('slot', 0)));

        $imageId = $imageModel->create([
            'filename'           => $result['filename'],
            'original_filename'  => $file['name'],
            'filepath'           => $storedOptimized,
            'original_filepath'  => $storedOriginal,
            'thumb_filepath'     => $storedThumb,
            'filesize'           => $result['optimized_size'],
            'original_filesize'  => $result['original_size'],
            'optimized_filesize' => $result['optimized_size'],
            'optimization_ratio' => $result['ratio'],
            'width'              => $result['width'],
            'height'             => $result['height'],
            'mime_type'          => $result['mime_type'],
            'brand_id'           => $brand['id'],
            'location_id'        => $location['id'],
            'uploaded_by'        => $userId,
            'slot'               => $slot ?: null,
        ]);

        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'upload', 'image', $imageId, [
            'original_filename' => $file['name'],
            'brand'             => $brand['name'],
            'location'          => $location['name'],
        ]);

        $base = rtrim(env('APP_URL', ''), '/') . '/storage/images/' . $brand['slug'];

        $this->json([
            'success'           => true,
            'image_id'          => $imageId,
            'thumb_url'         => $this->resolveUrl($storedThumb, $base),
            'optimized_url'     => $this->resolveUrl($storedOptimized, $base),
            'original_filename' => $file['name'],
            'filesize_human'    => formatBytes($result['optimized_size']),
            'download_url'      => '/download/' . $imageId,
        ]);
    }

    public function uploadSign(Request $request, array $params = []): void
    {
        $this->requirePermission('upload');
        $this->requireCsrf();

        [$brand, $location] = $this->loadBrandLocation(
            (int) ($params['id']     ?? 0),
            (int) ($params['loc_id'] ?? 0)
        );

        $imageModel   = new Image();
        $currentCount = $imageModel->countByLocation($brand['id'], $location['id']);
        if ($currentCount >= self::MAX_PHOTOS) {
            $this->json(['success' => false, 'error' => 'Limite de ' . self::MAX_PHOTOS . ' fotos atingido.'], 422);
        }

        $mime   = $request->post('mime', 'image/jpeg');
        $extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
                   'image/gif'  => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp'];
        $ext    = $extMap[$mime] ?? 'jpg';

        $storage = new SupabaseStorage();
        if (!$storage->isConfigured()) {
            $this->json(['success' => false, 'error' => 'Storage não configurado.'], 500);
        }

        try {
            $baseName  = uuid4();
            $path      = $brand['slug'] . '/' . $baseName . '.' . $ext;
            $signed    = $storage->createSignedUploadUrl($path);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'Erro ao gerar URL: ' . $e->getMessage()], 500);
        }

        $this->json([
            'success'    => true,
            'signed_url' => $signed['signed_url'],
            'public_url' => $signed['public_url'],
            'filename'   => $baseName . '.' . $ext,
        ]);
    }

    public function uploadConfirm(Request $request, array $params = []): void
    {
        $this->requirePermission('upload');
        $this->requireCsrf();

        [$brand, $location] = $this->loadBrandLocation(
            (int) ($params['id']     ?? 0),
            (int) ($params['loc_id'] ?? 0)
        );

        $publicUrl        = $request->post('public_url', '');
        $originalFilename = $request->post('original_filename', '');
        $filename         = $request->post('filename', '');
        $filesize         = (int) $request->post('filesize', 0);
        $width            = (int) $request->post('width', 0);
        $height           = (int) $request->post('height', 0);
        $mime             = $request->post('mime', 'image/jpeg');
        $slot             = max(1, min(self::MAX_PHOTOS, (int) $request->post('slot', 0)));

        if (!str_starts_with($publicUrl, 'http')) {
            $this->json(['success' => false, 'error' => 'URL inválido.'], 422);
        }

        $user   = $this->auth->user();
        $userId = (!empty($user['id']) && $user['id'] > 0) ? $user['id'] : null;

        $imageModel = new Image();
        $imageId    = $imageModel->create([
            'filename'           => $filename,
            'original_filename'  => $originalFilename,
            'filepath'           => $publicUrl,
            'original_filepath'  => $publicUrl,
            'thumb_filepath'     => $publicUrl,
            'filesize'           => $filesize,
            'original_filesize'  => $filesize,
            'optimized_filesize' => $filesize,
            'optimization_ratio' => 0,
            'width'              => $width,
            'height'             => $height,
            'mime_type'          => $mime,
            'brand_id'           => $brand['id'],
            'location_id'        => $location['id'],
            'uploaded_by'        => $userId,
            'slot'               => $slot ?: null,
        ]);

        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'upload', 'image', $imageId, [
            'original_filename' => $originalFilename,
            'brand'             => $brand['name'],
            'location'          => $location['name'],
        ]);

        $this->json([
            'success'           => true,
            'image_id'          => $imageId,
            'thumb_url'         => $publicUrl,
            'optimized_url'     => $publicUrl,
            'original_filename' => $originalFilename,
            'filesize_human'    => formatBytes($filesize),
            'download_url'      => '/download/' . $imageId,
        ]);
    }

    public function delete(Request $request, array $params = []): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id         = (int) ($params['id'] ?? 0);
        $imageModel = new Image();
        $image      = $imageModel->findWithRelations($id);

        if (!$image || $image['deleted_at'] !== null) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        $user      = $this->auth->user();
        $canDelete = $this->auth->can('delete_any')
            || ($this->auth->can('delete_own') && (int) $image['uploaded_by'] === (int) $user['id']);

        if (!$canDelete) {
            $this->json(['success' => false, 'error' => 'Sem permissão para eliminar esta imagem.'], 403);
        }

        $imageModel->softDelete($id);

        // Delete files from Supabase Storage
        $storage = new SupabaseStorage();
        if ($storage->isConfigured()) {
            $paths = array_unique(array_filter([
                $image['filepath']          ?? '',
                $image['thumb_filepath']    ?? '',
                $image['original_filepath'] ?? '',
            ], fn($p) => str_starts_with($p, 'http')));

            if (!empty($paths)) {
                $storagePaths = array_map([$storage, 'pathFromUrl'], $paths);
                try {
                    $storage->delete($storagePaths);
                } catch (\Throwable $e) {
                    error_log('SupabaseStorage::delete failed: ' . $e->getMessage());
                }
            }
        }

        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'image_delete', 'image', $id, [
            'filename' => $image['original_filename'],
        ]);

        $this->json(['success' => true]);
    }

    private function resolveUrl(string $path, string $base): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return $path !== '' ? $base . '/' . basename($path) : '';
    }

    private function loadBrandLocation(int $brandId, int $locationId): array
    {
        $brand = (new Brand())->find($brandId);
        if (!$brand) {
            http_response_code(404);
            require __DIR__ . '/../Views/errors/404.php';
            exit;
        }

        $location = (new Location())->find($locationId);
        if (!$location || (int) ($location['brand_id'] ?? 0) !== $brandId) {
            http_response_code(404);
            require __DIR__ . '/../Views/errors/404.php';
            exit;
        }

        return [$brand, $location];
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ficheiro demasiado grande.',
            UPLOAD_ERR_PARTIAL   => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE   => 'Nenhum ficheiro enviado.',
            default              => 'Erro no upload.',
        };
    }
}
