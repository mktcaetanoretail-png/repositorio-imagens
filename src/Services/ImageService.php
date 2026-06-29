<?php

declare(strict_types=1);

namespace App\Services;

class ImageService
{
    private bool $useImagick;

    // Max dimension before resizing (px)
    private const MAX_DIMENSION = 3840;
    // Thumb dimensions
    private const THUMB_W = 400;
    private const THUMB_H = 300;
    // Default compression quality
    private const DEFAULT_QUALITY = 82;

    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'  => 'bmp',
        'image/tiff' => 'tiff',
    ];

    private bool $useGd;

    public function __construct()
    {
        $this->useImagick = extension_loaded('imagick');
        $this->useGd      = function_exists('imagecreatefromjpeg');
    }

    /**
     * Full optimize pipeline.
     * Returns array with keys: optimized_path, thumb_path, original_path,
     * original_size, optimized_size, ratio, width, height, mime_type
     */
    public function optimize(string $sourcePath, string $destDir): array
    {
        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source file not found: {$sourcePath}");
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $originalSize = filesize($sourcePath);
        $mime         = $this->detectMime($sourcePath);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new \RuntimeException("Unsupported MIME type: {$mime}");
        }

        $baseName = uuid4();

        if ($this->useImagick) {
            $origFilename = 'original_' . $baseName . '.' . self::ALLOWED_MIMES[$mime];
            $origPath     = $destDir . '/' . $origFilename;
            copy($sourcePath, $origPath);
            $result = $this->optimizeWithImagick($sourcePath, $destDir, $baseName, $mime);
        } elseif ($this->useGd) {
            $origFilename = 'original_' . $baseName . '.' . self::ALLOWED_MIMES[$mime];
            $origPath     = $destDir . '/' . $origFilename;
            copy($sourcePath, $origPath);
            $result = $this->optimizeWithGd($sourcePath, $destDir, $baseName, $mime);
        } else {
            // No image library available — store one copy and reuse for all variants
            $result   = $this->storeAsIs($sourcePath, $destDir, $baseName, $mime);
            $origPath = $result['optimized_path'];
        }

        $optimizedSize = filesize($result['optimized_path']);
        $ratio = $originalSize > 0
            ? round((1 - ($optimizedSize / $originalSize)) * 100, 2)
            : 0.0;

        return array_merge($result, [
            'original_path'  => $origPath,
            'original_size'  => $originalSize,
            'optimized_size' => $optimizedSize,
            'ratio'          => $ratio,
            'mime_type'      => $mime,
        ]);
    }

    private function storeAsIs(string $sourcePath, string $destDir, string $baseName, string $mime): array
    {
        $ext         = self::ALLOWED_MIMES[$mime] ?? 'jpg';
        $outFilename = $baseName . '.' . $ext;
        $outPath     = $destDir . '/' . $outFilename;

        copy($sourcePath, $outPath);

        $size = getimagesize($sourcePath);
        if ($size === false) {
            throw new \RuntimeException("Ficheiro de imagem inválido ou corrompido: {$sourcePath}");
        }

        return [
            'optimized_path' => $outPath,
            'thumb_path'     => $outPath,
            'filename'       => $outFilename,
            'thumb_filename' => $outFilename,
            'width'          => $size[0],
            'height'         => $size[1],
        ];
    }

    private function optimizeWithGd(string $sourcePath, string $destDir, string $baseName, string $mime): array
    {
        $image = $this->gdCreate($sourcePath, $mime);

        $origW = imagesx($image);
        $origH = imagesy($image);

        // Resize if needed (never upscale)
        [$newW, $newH] = $this->calcDimensions($origW, $origH, self::MAX_DIMENSION);

        if ($newW !== $origW || $newH !== $origH) {
            $resized = imagecreatetruecolor($newW, $newH);
            $this->gdPreserveTransparency($resized);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $resized;
        }

        $finalW = imagesx($image);
        $finalH = imagesy($image);

        // Convert PNG without alpha to JPG; keep PNG if it has transparency
        $haAlpha = $mime === 'image/png' && $this->gdHasTransparency($image);
        $outExt  = (!$haAlpha && in_array($mime, ['image/png', 'image/bmp', 'image/tiff'], true))
            ? 'jpg'
            : self::ALLOWED_MIMES[$mime];

        // For webp/gif keep as-is
        if (in_array($mime, ['image/webp', 'image/gif'], true)) {
            $outExt = self::ALLOWED_MIMES[$mime];
        }

        $outFilename = $baseName . '.' . $outExt;
        $outPath     = $destDir . '/' . $outFilename;

        if ($outExt === 'jpg') {
            // Convert to true colour, white background
            $bg = imagecreatetruecolor($finalW, $finalH);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $image, 0, 0, 0, 0, $finalW, $finalH);
            imagedestroy($image);
            imageinterlace($bg, 1); // progressive
            imagejpeg($bg, $outPath, self::DEFAULT_QUALITY);
            imagedestroy($bg);
        } elseif ($outExt === 'png') {
            imagepng($image, $outPath, 8);
            imagedestroy($image);
        } elseif ($outExt === 'webp') {
            imagewebp($image, $outPath, self::DEFAULT_QUALITY);
            imagedestroy($image);
        } else {
            imagejpeg($image, $outPath, self::DEFAULT_QUALITY);
            imagedestroy($image);
        }

        // Generate thumbnail
        $thumbFilename = 'thumb_' . $baseName . '.jpg';
        $thumbPath     = $destDir . '/' . $thumbFilename;
        $this->generateThumbGd($outPath, $thumbPath, self::THUMB_W, self::THUMB_H);

        return [
            'optimized_path'  => $outPath,
            'thumb_path'      => $thumbPath,
            'filename'        => $outFilename,
            'thumb_filename'  => $thumbFilename,
            'width'           => $finalW,
            'height'          => $finalH,
        ];
    }

    private function optimizeWithImagick(string $sourcePath, string $destDir, string $baseName, string $mime): array
    {
        $imagick = new \Imagick($sourcePath);

        // Strip all metadata (EXIF, IPTC, XMP, etc.)
        $imagick->stripImage();

        // Flatten for formats with transparency we convert to JPG
        $origW = $imagick->getImageWidth();
        $origH = $imagick->getImageHeight();

        // Resize if needed
        [$newW, $newH] = $this->calcDimensions($origW, $origH, self::MAX_DIMENSION);
        if ($newW !== $origW || $newH !== $origH) {
            $imagick->resizeImage($newW, $newH, \Imagick::FILTER_LANCZOS, 1);
        }

        $finalW = $imagick->getImageWidth();
        $finalH = $imagick->getImageHeight();

        // Decide output format
        $haAlpha = $imagick->getImageAlphaChannel() && in_array($mime, ['image/png'], true);
        $outExt  = (!$haAlpha && in_array($mime, ['image/png', 'image/bmp', 'image/tiff'], true))
            ? 'jpg'
            : self::ALLOWED_MIMES[$mime];

        if (in_array($mime, ['image/webp', 'image/gif'], true)) {
            $outExt = self::ALLOWED_MIMES[$mime];
        }

        $outFilename = $baseName . '.' . $outExt;
        $outPath     = $destDir . '/' . $outFilename;

        if ($outExt === 'jpg') {
            $imagick->setImageFormat('jpeg');
            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE); // progressive
            $imagick->setImageCompressionQuality(self::DEFAULT_QUALITY);
            // Flatten (remove alpha)
            $imagick->setImageBackgroundColor('white');
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        } elseif ($outExt === 'webp') {
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(self::DEFAULT_QUALITY);
        } elseif ($outExt === 'png') {
            $imagick->setImageFormat('png');
        }

        $imagick->writeImage($outPath);

        // Thumbnail
        $thumbFilename = 'thumb_' . $baseName . '.jpg';
        $thumbPath     = $destDir . '/' . $thumbFilename;
        $this->generateThumbImagick($outPath, $thumbPath, self::THUMB_W, self::THUMB_H);

        $imagick->clear();
        $imagick->destroy();

        return [
            'optimized_path'  => $outPath,
            'thumb_path'      => $thumbPath,
            'filename'        => $outFilename,
            'thumb_filename'  => $thumbFilename,
            'width'           => $finalW,
            'height'          => $finalH,
        ];
    }

    /**
     * Convert an image to specified format/quality/max_width.
     * Returns path to the converted temp file.
     */
    public function convert(string $sourcePath, array $options): string
    {
        $format   = $options['format']    ?? 'jpg';
        $quality  = (int) ($options['quality']   ?? self::DEFAULT_QUALITY);
        $maxWidth = isset($options['max_width']) ? (int) $options['max_width'] : null;

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source file not found: {$sourcePath}");
        }

        $mime  = $this->detectMime($sourcePath);
        $outPath = tempnam(sys_get_temp_dir(), 'conv_') . '.' . $format;

        if ($this->useImagick) {
            return $this->convertWithImagick($sourcePath, $outPath, $format, $quality, $maxWidth);
        }

        if ($this->useGd) {
            return $this->convertWithGd($sourcePath, $outPath, $mime, $format, $quality, $maxWidth);
        }

        copy($sourcePath, $outPath);
        return $outPath;
    }

    private function convertWithGd(
        string $src,
        string $dest,
        string $mime,
        string $format,
        int $quality,
        ?int $maxWidth
    ): string {
        $image = $this->gdCreate($src, $mime);
        $w = imagesx($image);
        $h = imagesy($image);

        if ($maxWidth && $w > $maxWidth) {
            $newH = (int) round($h * ($maxWidth / $w));
            $resized = imagecreatetruecolor($maxWidth, $newH);
            $this->gdPreserveTransparency($resized);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            imagedestroy($image);
            $image = $resized;
        }

        $fw = imagesx($image);
        $fh = imagesy($image);

        match ($format) {
            'jpg' => (function () use ($image, $dest, $quality, $fw, $fh) {
                $bg = imagecreatetruecolor($fw, $fh);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $image, 0, 0, 0, 0, $fw, $fh);
                imageinterlace($bg, 1);
                imagejpeg($bg, $dest, $quality);
                imagedestroy($bg);
                imagedestroy($image);
            })(),
            'png' => (function () use ($image, $dest) {
                imagepng($image, $dest, 8);
                imagedestroy($image);
            })(),
            'webp' => (function () use ($image, $dest, $quality) {
                imagewebp($image, $dest, $quality);
                imagedestroy($image);
            })(),
            default => (function () use ($image, $dest, $quality) {
                imagejpeg($image, $dest, $quality);
                imagedestroy($image);
            })(),
        };

        return $dest;
    }

    private function convertWithImagick(
        string $src,
        string $dest,
        string $format,
        int $quality,
        ?int $maxWidth
    ): string {
        $imagick = new \Imagick($src);
        $imagick->stripImage();

        if ($maxWidth && $imagick->getImageWidth() > $maxWidth) {
            $imagick->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1);
        }

        $imagick->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
        $imagick->setImageCompressionQuality($quality);

        if ($format === 'jpg') {
            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
            $imagick->setImageBackgroundColor('white');
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        }

        $imagick->writeImage($dest);
        $imagick->clear();
        $imagick->destroy();

        return $dest;
    }

    /**
     * Generate a center-cropped thumbnail.
     */
    public function generateThumb(string $sourcePath, int $w, int $h): string
    {
        $mime    = $this->detectMime($sourcePath);
        $outPath = tempnam(sys_get_temp_dir(), 'thumb_') . '.jpg';

        if ($this->useImagick) {
            $this->generateThumbImagick($sourcePath, $outPath, $w, $h);
        } elseif ($this->useGd) {
            $this->generateThumbGd($sourcePath, $outPath, $w, $h);
        } else {
            copy($sourcePath, $outPath);
        }

        return $outPath;
    }

    private function generateThumbGd(string $sourcePath, string $destPath, int $tw, int $th): void
    {
        $mime  = $this->detectMime($sourcePath);
        $image = $this->gdCreate($sourcePath, $mime);
        $iw    = imagesx($image);
        $ih    = imagesy($image);

        // Determine crop area (center-crop)
        $srcRatio  = $iw / $ih;
        $destRatio = $tw / $th;

        if ($srcRatio > $destRatio) {
            // Source is wider — crop sides
            $cropH = $ih;
            $cropW = (int) round($ih * $destRatio);
            $cropX = (int) round(($iw - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source is taller — crop top/bottom
            $cropW = $iw;
            $cropH = (int) round($iw / $destRatio);
            $cropX = 0;
            $cropY = (int) round(($ih - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($tw, $th);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $image, 0, 0, $cropX, $cropY, $tw, $th, $cropW, $cropH);
        imagedestroy($image);

        imageinterlace($thumb, 1);
        imagejpeg($thumb, $destPath, 80);
        imagedestroy($thumb);
    }

    private function generateThumbImagick(string $sourcePath, string $destPath, int $tw, int $th): void
    {
        $imagick = new \Imagick($sourcePath);
        $imagick->stripImage();
        $imagick->cropThumbnailImage($tw, $th);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(80);
        $imagick->writeImage($destPath);
        $imagick->clear();
        $imagick->destroy();
    }

    /**
     * Strip metadata from an image file in place.
     */
    public function stripMetadata(string $sourcePath): void
    {
        if ($this->useImagick) {
            $imagick = new \Imagick($sourcePath);
            $imagick->stripImage();
            $imagick->writeImage($sourcePath);
            $imagick->clear();
            $imagick->destroy();
            return;
        }

        // GD re-encode (strips metadata implicitly)
        $mime  = $this->detectMime($sourcePath);
        $image = $this->gdCreate($sourcePath, $mime);

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($image, $sourcePath, self::DEFAULT_QUALITY);
                break;
            case 'image/png':
                imagepng($image, $sourcePath, 8);
                break;
            case 'image/webp':
                imagewebp($image, $sourcePath, self::DEFAULT_QUALITY);
                break;
        }

        imagedestroy($image);
    }

    /**
     * Estimate the output filesize for given conversion options without saving.
     * Returns estimated bytes.
     */
    public function estimateSize(string $sourcePath, array $options): int
    {
        try {
            $tmpPath = $this->convert($sourcePath, $options);
            $size    = filesize($tmpPath);
            @unlink($tmpPath);
            return (int) $size;
        } catch (\Throwable) {
            return 0;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function gdCreate(string $path, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png'               => imagecreatefrompng($path),
            'image/gif'               => imagecreatefromgif($path),
            'image/webp'              => imagecreatefromwebp($path),
            'image/bmp'               => imagecreatefrombmp($path),
            default                   => imagecreatefromjpeg($path),
        };

        if (!$image) {
            throw new \RuntimeException("Failed to create GD image from: {$path}");
        }

        return $image;
    }

    private function gdPreserveTransparency(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }

    private function gdHasTransparency(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        // Sample corners and centre
        $samples = [
            [0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
            [(int)($w / 2), (int)($h / 2)],
        ];
        foreach ($samples as [$x, $y]) {
            $rgba = imagecolorat($image, $x, $y);
            if ($rgba !== false) {
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }
        // Fallback: extension-based
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            default       => 'image/jpeg',
        };
    }

    private function calcDimensions(int $w, int $h, int $max): array
    {
        if ($w <= $max && $h <= $max) {
            return [$w, $h];
        }
        if ($w >= $h) {
            $newW = $max;
            $newH = (int) round($h * ($max / $w));
        } else {
            $newH = $max;
            $newW = (int) round($w * ($max / $h));
        }
        return [$newW, $newH];
    }

    public static function getAllowedMimes(): array
    {
        return array_keys(self::ALLOWED_MIMES);
    }
}
