<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Brand;
use App\Models\Image;
use App\Models\Location;
use App\Services\ImageService;

class ConverterController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $this->requirePermission('convert');

        $brandModel    = new Brand();
        $locationModel = new Location();

        // Gallery images for source selection
        $imageModel = new Image();
        $filters    = [
            'brand_id'    => $request->get('brand_id'),
            'location_id' => $request->get('location_id'),
            'search'      => trim($request->get('search', '')),
        ];
        foreach ($filters as $k => $v) {
            if ($v === '' || $v === null) {
                unset($filters[$k]);
            }
        }

        $images    = $imageModel->searchGallery($filters, 1, 100);
        $appUrl    = rtrim(env('APP_URL', ''), '/');

        $images = array_map(function ($img) use ($appUrl) {
            $brandSlug = $img['brand_slug'] ?? slugify($img['brand_name'] ?? 'unknown');
            $img['thumb_url'] = $appUrl . '/storage/images/' . $brandSlug . '/' . basename($img['thumb_filepath'] ?? '');
            $img['filesize_human'] = formatBytes((int) ($img['filesize'] ?? 0));
            return $img;
        }, $images);

        $this->render('converter/index', [
            'images'     => $images,
            'brands'     => $brandModel->findAll([], 'name ASC'),
            'locations'  => $locationModel->findAll([], 'name ASC'),
            'filters'    => $filters,
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function process(Request $request, array $params = []): void
    {
        $this->requirePermission('convert');
        $this->requireCsrf();

        $imageId  = (int) $request->post('image_id', 0);
        $format   = $request->post('format', 'jpg');
        $quality  = min(100, max(1, (int) $request->post('quality', 82)));
        $maxWidth = $request->post('max_width', '');
        $maxWidth = $maxWidth !== '' ? max(1, (int) $maxWidth) : null;

        if (!in_array($format, ['jpg', 'png', 'webp'], true)) {
            $this->json(['success' => false, 'error' => 'Formato inválido.'], 422);
        }

        $imageModel = new Image();
        $image      = $imageModel->find($imageId);

        if (!$image || $image['deleted_at'] !== null) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $absPath     = $this->resolvePath($image['filepath'], $storageBase);

        if (!file_exists($absPath)) {
            $this->json(['success' => false, 'error' => 'Ficheiro fonte não encontrado.'], 404);
        }

        try {
            $svc     = new ImageService();
            $tmpPath = $svc->convert($absPath, [
                'format'    => $format,
                'quality'   => $quality,
                'max_width' => $maxWidth,
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'Erro na conversão: ' . $e->getMessage()], 500);
        }

        $originalName  = pathinfo($image['original_filename'], PATHINFO_FILENAME);
        $downloadName  = $originalName . '_converted.' . $format;
        $convertedSize = filesize($tmpPath);

        $mimeMap = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $mime    = $mimeMap[$format] ?? 'image/jpeg';

        // Stream converted file, delete temp after
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
        header('Content-Length: ' . $convertedSize);
        header('X-Original-Size: ' . $image['filesize']);
        header('X-Converted-Size: ' . $convertedSize);
        header('Cache-Control: no-cache');

        readfile($tmpPath);
        @unlink($tmpPath);
        exit;
    }

    public function estimate(Request $request, array $params = []): void
    {
        $this->requirePermission('convert');
        $this->requireCsrf();

        $imageId  = (int) $request->post('image_id', 0);
        $format   = $request->post('format', 'jpg');
        $quality  = min(100, max(1, (int) $request->post('quality', 82)));
        $maxWidth = $request->post('max_width', '');
        $maxWidth = $maxWidth !== '' ? max(1, (int) $maxWidth) : null;

        if (!in_array($format, ['jpg', 'png', 'webp'], true)) {
            $this->json(['success' => false, 'error' => 'Formato inválido.'], 422);
        }

        $imageModel = new Image();
        $image      = $imageModel->find($imageId);

        if (!$image) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $absPath     = $this->resolvePath($image['filepath'], $storageBase);

        if (!file_exists($absPath)) {
            $this->json(['success' => false, 'error' => 'Ficheiro não encontrado.'], 404);
        }

        $svc           = new ImageService();
        $estimatedSize = $svc->estimateSize($absPath, [
            'format'    => $format,
            'quality'   => $quality,
            'max_width' => $maxWidth,
        ]);

        $originalSize = (int) $image['filesize'];
        $savings      = $originalSize > 0 ? round((1 - ($estimatedSize / $originalSize)) * 100, 1) : 0;

        $this->json([
            'success'        => true,
            'original_size'  => $originalSize,
            'estimated_size' => $estimatedSize,
            'original_human' => formatBytes($originalSize),
            'estimated_human'=> formatBytes($estimatedSize),
            'savings_pct'    => $savings,
        ]);
    }

    private function resolvePath(string $filePath, string $storageBase): string
    {
        if (file_exists($filePath)) {
            return $filePath;
        }
        $candidate = rtrim($storageBase, '/') . '/' . ltrim(basename($filePath), '/');
        return file_exists($candidate) ? $candidate : $filePath;
    }
}
