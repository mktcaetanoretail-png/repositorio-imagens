<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Image;

class ZipService
{
    private Image $imageModel;

    public function __construct()
    {
        $this->imageModel = new Image();
    }

    /**
     * Create a ZIP archive from the given image IDs.
     *
     * @param int[]  $imageIds  List of image IDs to include
     * @param string $version   'optimized' or 'original'
     * @return string Path to the created ZIP file in /tmp
     * @throws \RuntimeException if ZipArchive is not available or creation fails
     */
    public function createFromImages(array $imageIds, string $version = 'optimized'): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive extension is not available.');
        }

        if (empty($imageIds)) {
            throw new \RuntimeException('No image IDs provided.');
        }

        $images = $this->imageModel->findByIds($imageIds);

        if (empty($images)) {
            throw new \RuntimeException('No valid images found for the given IDs.');
        }

        // Create temp ZIP file
        $zipPath = tempnam(sys_get_temp_dir(), 'download_') . '.zip';
        $zip     = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive.');
        }

        $storageBase = env('STORAGE_PATH', dirname(__DIR__, 2) . '/storage/images');
        $nameCount   = [];

        foreach ($images as $image) {
            if ($version === 'original') {
                $filePath = $image['original_filepath'];
                $baseName = 'original_' . $image['original_filename'];
            } else {
                $filePath = $image['filepath'];
                $baseName = $image['filename'];
            }

            // Build absolute path
            $absPath = $this->resolvePath($filePath, $storageBase);

            if (!file_exists($absPath)) {
                // Silently skip missing files
                continue;
            }

            // Avoid duplicate names in the ZIP
            $uniqueName = $this->uniqueName($baseName, $nameCount);
            $nameCount[$baseName] = ($nameCount[$baseName] ?? 0) + 1;

            // Organise by brand in the ZIP
            $folder = $this->sanitizeFolderName($image['brand_name'] ?? 'unknown');
            $zip->addFile($absPath, $folder . '/' . $uniqueName);
        }

        $zip->close();

        return $zipPath;
    }

    private function resolvePath(string $filePath, string $storageBase): string
    {
        // If absolute path exists, use directly
        if (file_exists($filePath)) {
            return $filePath;
        }

        // Try relative to storage base
        $candidate = rtrim($storageBase, '/') . '/' . ltrim($filePath, '/');
        if (file_exists($candidate)) {
            return $candidate;
        }

        // Return original (will be checked by caller)
        return $filePath;
    }

    private function uniqueName(string $name, array $existing): string
    {
        if (!isset($existing[$name]) || $existing[$name] === 0) {
            return $name;
        }

        $ext      = pathinfo($name, PATHINFO_EXTENSION);
        $base     = pathinfo($name, PATHINFO_FILENAME);
        $counter  = $existing[$name] + 1;

        return $ext ? "{$base}_{$counter}.{$ext}" : "{$base}_{$counter}";
    }

    private function sanitizeFolderName(string $name): string
    {
        // Remove characters invalid in ZIP folder names
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        return trim($name, ' .');
    }
}
