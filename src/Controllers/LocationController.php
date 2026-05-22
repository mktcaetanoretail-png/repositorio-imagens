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
        $images     = $imageModel->findByLocation($brand['id'], $location['id']);
        $base       = rtrim(env('APP_URL', ''), '/') . '/storage/images/' . $brand['slug'];

        foreach ($images as &$img) {
            $img['thumb_url']     = $base . '/' . basename($img['thumb_filepath']     ?? '');
            $img['optimized_url'] = $base . '/' . basename($img['filepath']           ?? '');
            $img['original_url']  = $base . '/' . basename($img['original_filepath']  ?? '');
            $img['download_url']  = '/download/' . $img['id'];
            $img['filesize_human']= formatBytes((int) ($img['filesize'] ?? 0));
        }
        unset($img);

        // Load all brand locations for the sidebar
        $locationModel  = new Location();
        $brandLocations = $locationModel->findByBrand($brand['id']);
        foreach ($brandLocations as &$loc) {
            $loc['image_count'] = $imageModel->countByLocation($brand['id'], $loc['id']);
        }
        unset($loc);

        $this->render('locations/photos', [
            'brand'             => $brand,
            'location'          => $location,
            'images'            => $images,
            'brandLocations'    => $brandLocations,
            'max_photos'        => self::MAX_PHOTOS,
            'slots_available'   => max(0, self::MAX_PHOTOS - count($images)),
            'pageTitle'         => $location['name'] . ' — ' . $brand['name'],
            'flash_ok'          => $this->getFlash('success'),
            'flash_error'       => $this->getFlash('error'),
            'csrf_token'        => $this->csrfToken(),
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

        $maxBytes = ((int) env('UPLOAD_MAX_SIZE_MB', 20)) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $this->json(['success' => false, 'error' => 'Ficheiro demasiado grande. Máximo: ' . env('UPLOAD_MAX_SIZE_MB', 20) . ' MB.'], 422);
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

        $imageId = $imageModel->create([
            'filename'           => $result['filename'],
            'original_filename'  => $file['name'],
            'filepath'           => $result['optimized_path'],
            'original_filepath'  => $result['original_path'],
            'thumb_filepath'     => $result['thumb_path'],
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
            'thumb_url'         => $base . '/' . basename($result['thumb_path']),
            'optimized_url'     => $base . '/' . basename($result['optimized_path']),
            'original_filename' => $file['name'],
            'filesize_human'    => formatBytes($result['optimized_size']),
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

        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'image_delete', 'image', $id, [
            'filename' => $image['original_filename'],
        ]);

        $this->json(['success' => true]);
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
