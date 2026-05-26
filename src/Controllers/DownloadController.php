<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\Image;
use App\Services\ZipService;

class DownloadController extends Controller
{
    public function single(Request $request, array $params = []): void
    {
        $this->requirePermission('download');

        $id      = (int) ($params['id'] ?? 0);
        $version = $request->get('version', 'optimized'); // 'optimized' | 'original'

        $imageModel = new Image();
        $image      = $imageModel->findWithRelations($id);

        if (!$image || $image['deleted_at'] !== null) {
            http_response_code(404);
            echo 'Imagem não encontrada.';
            exit;
        }

        // Original version requires download_original permission
        if ($version === 'original' && !$this->auth->can('download_original')) {
            http_response_code(403);
            echo 'Sem permissão para transferir o ficheiro original.';
            exit;
        }

        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $brandSlug   = $image['brand_slug'];

        if ($version === 'original') {
            $filePath = $image['original_filepath'];
            $fileName = 'original_' . $image['original_filename'];
        } else {
            $filePath = $image['filepath'];
            $fileName = $image['filename'];
        }

        // Log download
        $user = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'download', 'image', $id, [
            'version'  => $version,
            'filename' => $fileName,
        ]);

        // If stored as a URL (Supabase Storage), proxy it with attachment header
        if (str_starts_with($filePath, 'http')) {
            $this->proxyRemoteFile($filePath, $image['original_filename']);
        }

        // Resolve absolute path for local/disk storage
        $absPath = $this->resolvePath($filePath, $storageBase, $brandSlug);

        if (!file_exists($absPath)) {
            http_response_code(404);
            echo 'Ficheiro não encontrado no sistema de ficheiros.';
            exit;
        }

        // Stream file
        $this->streamFile($absPath, $fileName);
    }

    public function bulk(Request $request, array $params = []): void
    {
        $this->requirePermission('download');
        $this->requireCsrf();

        $ids     = $request->post('ids', []);
        $version = $request->post('version', 'optimized');

        if (!is_array($ids) || empty($ids)) {
            $this->json(['success' => false, 'error' => 'Nenhuma imagem seleccionada.'], 422);
        }

        if ($version === 'original' && !$this->auth->can('download_original')) {
            $this->json(['success' => false, 'error' => 'Sem permissão para transferir ficheiros originais.'], 403);
        }

        $ids = array_filter(array_map('intval', $ids));

        if (count($ids) > 200) {
            $this->json(['success' => false, 'error' => 'Máximo de 200 imagens por transferência.'], 422);
        }

        try {
            $zipService = new ZipService();
            $zipPath    = $zipService->createFromImages($ids, $version);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        // Log bulk download
        $user = $this->auth->user();
        $auditLog = new AuditLog();
        $auditLog->log($user['id'], 'bulk_download', 'image', null, [
            'count'   => count($ids),
            'version' => $version,
        ]);

        $filename = 'imagens_' . date('Ymd_His') . '.zip';
        $this->streamFile($zipPath, $filename, 'application/zip', true);
    }

    private function proxyRemoteFile(string $url, string $originalFilename): never
    {
        // Stream via temp file to avoid buffering entire response in memory
        $tmpPath = tempnam(sys_get_temp_dir(), 'dl_');

        $fp = fopen($tmpPath, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        $error  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($error !== '' || $status !== 200) {
            @unlink($tmpPath);
            http_response_code(502);
            echo 'Erro ao obter o ficheiro.';
            exit;
        }

        $this->streamFile($tmpPath, $originalFilename, $mime, true);
    }

    private function resolvePath(string $filePath, string $storageBase, string $brandSlug): string
    {
        if (file_exists($filePath)) {
            return $filePath;
        }

        $candidate = rtrim($storageBase, '/') . '/' . $brandSlug . '/' . basename($filePath);
        if (file_exists($candidate)) {
            return $candidate;
        }

        return $filePath;
    }

    private function streamFile(string $path, string $filename, string $mime = '', bool $deleteAfter = false): never
    {
        if (empty($mime)) {
            $mime = mime_content_type($path) ?: 'application/octet-stream';
        }

        $size = filesize($path);

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($path);

        if ($deleteAfter) {
            @unlink($path);
        }

        exit;
    }
}
