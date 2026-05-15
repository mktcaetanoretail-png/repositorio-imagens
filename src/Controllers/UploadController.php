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

class UploadController extends Controller
{
    private const MAX_SIZE_BYTES  = 20 * 1024 * 1024; // 20 MB default
    private const ALLOWED_MIMES   = [
        'image/jpeg', 'image/jpg', 'image/png',
        'image/gif', 'image/webp', 'image/bmp',
    ];

    public function showForm(Request $request, array $params = []): void
    {
        $this->requirePermission('upload');
        // Upload is handled via AJAX modal on the gallery page
        $this->redirect('/');
    }

    public function handle(Request $request, array $params = []): void
    {
        $this->requirePermission('upload');
        $this->requireCsrf();

        $brandId    = (int) $request->post('brand_id', 0);
        $locationId = (int) $request->post('location_id', 0);

        if (!$brandId || !$locationId) {
            $this->json(['success' => false, 'error' => 'Marca e localização são obrigatórias.'], 422);
        }

        // Validate brand & location exist
        $brandModel = new Brand();
        $brand      = $brandModel->find($brandId);
        if (!$brand) {
            $this->json(['success' => false, 'error' => 'Marca inválida.'], 422);
        }

        $locationModel = new Location();
        $location      = $locationModel->find($locationId);
        if (!$location) {
            $this->json(['success' => false, 'error' => 'Localização inválida.'], 422);
        }

        $uploadedFile = $request->file('image');
        if (!$uploadedFile || empty($uploadedFile['tmp_name'])) {
            $this->json(['success' => false, 'error' => 'Nenhum ficheiro recebido.'], 422);
        }

        // Validate upload error
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $this->json([
                'success' => false,
                'error'   => $this->uploadErrorMessage($uploadedFile['error']),
            ], 422);
        }

        // Validate size
        $maxBytes = ((int) env('UPLOAD_MAX_SIZE_MB', 20)) * 1024 * 1024;
        if ($uploadedFile['size'] > $maxBytes) {
            $this->json([
                'success' => false,
                'error'   => 'Ficheiro demasiado grande. Máximo: ' . env('UPLOAD_MAX_SIZE_MB', 20) . ' MB.',
            ], 422);
        }

        // Validate MIME (real, not spoofed)
        $mime = mime_content_type($uploadedFile['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            $this->json([
                'success' => false,
                'error'   => 'Tipo de ficheiro não suportado. Permitido: JPG, PNG, GIF, WEBP.',
            ], 422);
        }

        // Build storage directory
        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $brandSlug   = $brand['slug'];
        $destDir     = rtrim($storageBase, '/') . '/' . $brandSlug;

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            $this->json(['success' => false, 'error' => 'Erro ao criar pasta de destino.'], 500);
        }

        try {
            $svc    = new ImageService();
            $result = $svc->optimize($uploadedFile['tmp_name'], $destDir);
        } catch (\Throwable $e) {
            error_log('ImageService::optimize failed: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao processar imagem.'], 500);
        }

        $user = $this->auth->user();

        $imageModel = new Image();
        $imageId    = $imageModel->create([
            'filename'            => $result['filename'],
            'original_filename'   => $uploadedFile['name'],
            'filepath'            => $result['optimized_path'],
            'original_filepath'   => $result['original_path'],
            'thumb_filepath'      => $result['thumb_path'],
            'filesize'            => $result['optimized_size'],
            'original_filesize'   => $result['original_size'],
            'optimized_filesize'  => $result['optimized_size'],
            'optimization_ratio'  => $result['ratio'],
            'width'               => $result['width'],
            'height'              => $result['height'],
            'mime_type'           => $result['mime_type'],
            'brand_id'            => $brandId,
            'location_id'         => $locationId,
            'uploaded_by'         => $user['id'],
        ]);

        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'upload', 'image', $imageId, [
            'original_filename' => $uploadedFile['name'],
            'brand'             => $brand['name'],
            'location'          => $location['name'],
            'original_size'     => $result['original_size'],
            'optimized_size'    => $result['optimized_size'],
            'ratio'             => $result['ratio'],
        ]);

        $appUrl    = rtrim(env('APP_URL', ''), '/');
        $thumbUrl  = $appUrl . '/storage/images/' . $brandSlug . '/' . basename($result['thumb_path']);
        $optUrl    = $appUrl . '/storage/images/' . $brandSlug . '/' . basename($result['optimized_path']);

        $this->json([
            'success'              => true,
            'image_id'             => $imageId,
            'filename'             => $result['filename'],
            'original_filename'    => $uploadedFile['name'],
            'thumb_url'            => $thumbUrl,
            'optimized_url'        => $optUrl,
            'original_size'        => $result['original_size'],
            'optimized_size'       => $result['optimized_size'],
            'original_size_human'  => formatBytes($result['original_size']),
            'optimized_size_human' => formatBytes($result['optimized_size']),
            'ratio'                => $result['ratio'],
            'width'                => $result['width'],
            'height'               => $result['height'],
        ]);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ficheiro demasiado grande.',
            UPLOAD_ERR_PARTIAL   => 'O ficheiro foi apenas parcialmente enviado.',
            UPLOAD_ERR_NO_FILE   => 'Nenhum ficheiro foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Pasta temporária em falta no servidor.',
            UPLOAD_ERR_CANT_WRITE=> 'Falha ao escrever o ficheiro no servidor.',
            UPLOAD_ERR_EXTENSION => 'Extensão PHP bloqueou o upload.',
            default              => 'Erro desconhecido no upload.',
        };
    }
}
