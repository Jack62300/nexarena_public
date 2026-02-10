<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ServerService
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 Mo

    public function processPresentation(UploadedFile $file): ?string
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_IMAGE_MIMES, true) || $file->getSize() > self::MAX_IMAGE_SIZE) {
            return null;
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($this->projectDir . '/public/uploads/servers/presentations', $filename);

        return $filename;
    }

    public function processBanner(UploadedFile $file): ?string
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_IMAGE_MIMES, true) || $file->getSize() > self::MAX_IMAGE_SIZE) {
            return null;
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $targetPath = $this->projectDir . '/public/uploads/servers/banners';
        $file->move($targetPath, $filename);

        $fullPath = $targetPath . '/' . $filename;

        // Resize if needed (max 1920x400)
        if (extension_loaded('gd')) {
            $info = @getimagesize($fullPath);
            if ($info && ($info[0] > 1920 || $info[1] > 400)) {
                $this->resizeImage($fullPath, 1920, 400, $info);
            }
        }

        return $filename;
    }

    public function deleteFile(string $subpath, string $filename): void
    {
        $path = $this->projectDir . '/public/uploads/' . $subpath . '/' . basename($filename);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function resizeImage(string $path, int $maxWidth, int $maxHeight, array $info): void
    {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => null,
        };

        if (!$src) {
            return;
        }

        $origW = $info[0];
        $origH = $info[1];

        $ratio = min($maxWidth / $origW, $maxHeight / $origH);
        if ($ratio >= 1) {
            imagedestroy($src);
            return;
        }

        $newW = (int) ($origW * $ratio);
        $newH = (int) ($origH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($dst, $path, 90),
            IMAGETYPE_PNG => imagepng($dst, $path),
            IMAGETYPE_GIF => imagegif($dst, $path),
            IMAGETYPE_WEBP => imagewebp($dst, $path, 90),
            default => null,
        };

        imagedestroy($src);
        imagedestroy($dst);
    }
}
