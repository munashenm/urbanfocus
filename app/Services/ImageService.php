<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageService
{
    public function storeProductImage(UploadedFile $file, int $productId): string
    {
        $directory = 'products/'.$productId;
        $baseName = (string) Str::uuid();

        if ($this->canConvertWebp()) {
            $webpPath = $directory.'/'.$baseName.'.webp';
            if ($this->storeAsWebp($file, $webpPath, $directory, $baseName)) {
                return $webpPath;
            }
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = $directory.'/'.$baseName.'.'.$extension;
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Could not read uploaded image.');
        }

        $this->storeBytes($path, $contents);

        return $path;
    }

    public function storeProductImageFromBinary(string $contents, int $productId, string $extension = 'jpg'): ?string
    {
        if ($contents === '' || ! $this->looksLikeImage($contents)) {
            return null;
        }

        $extension = strtolower($extension);
        if (str_starts_with($contents, "\x89PNG")) {
            $extension = 'png';
        } elseif (str_starts_with($contents, 'RIFF')) {
            $extension = 'webp';
        } elseif (str_starts_with($contents, "\xFF\xD8\xFF")) {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $extension = 'jpg';
        }

        try {
            if ($this->canConvertWebp() && @imagecreatefromstring($contents) !== false) {
                $directory = 'products/'.$productId;
                $baseName = (string) Str::uuid();
                $webpPath = $directory.'/'.$baseName.'.webp';

                if ($this->storeBinaryAsWebp($contents, $webpPath, $directory, $baseName)) {
                    return $webpPath;
                }
            }

            $path = 'products/'.$productId.'/'.Str::uuid().'.'.$extension;
            $this->storeBytes($path, $contents);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);

        $publicFile = public_path('storage/'.ltrim($path, '/'));
        if (is_file($publicFile)) {
            @unlink($publicFile);
        }
    }

    public function storeProductImageFromUrl(string $url, int $productId): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'user_agent' => 'UrbanFocus-ProductImport/1.0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $contents = @file_get_contents($url, false, $context);
            if ($contents === false || $contents === '') {
                return null;
            }

            $extension = $this->extensionFromUrl($url);
            if (! $this->looksLikeImage($contents)) {
                return null;
            }

            if ($this->canConvertWebp() && @imagecreatefromstring($contents) !== false) {
                $directory = 'products/'.$productId;
                $baseName = (string) Str::uuid();
                $webpPath = $directory.'/'.$baseName.'.webp';

                if ($this->storeBinaryAsWebp($contents, $webpPath, $directory, $baseName)) {
                    return $webpPath;
                }
            }

            $path = 'products/'.$productId.'/'.Str::uuid().'.'.$extension;
            $this->storeBytes($path, $contents);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $extension : 'jpg';
    }

    protected function looksLikeImage(string $contents): bool
    {
        if (function_exists('getimagesizefromstring') && @getimagesizefromstring($contents) !== false) {
            return true;
        }

        return str_starts_with($contents, "\xFF\xD8\xFF")
            || str_starts_with($contents, "\x89PNG")
            || str_starts_with($contents, 'GIF')
            || str_starts_with($contents, 'RIFF');
    }

    protected function storeBinaryAsWebp(string $contents, string $path, string $directory, string $baseName): bool
    {
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return false;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $saved = imagewebp($image, null, 82);
        $webpData = ob_get_clean();
        imagedestroy($image);

        if (! $saved || $webpData === false) {
            return false;
        }

        $this->storeBytes($path, $webpData);
        $this->storeThumbnail($webpData, $directory.'/'.$baseName.'_thumb.webp');

        return true;
    }

    protected function canConvertWebp(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    protected function storeAsWebp(UploadedFile $file, string $path, string $directory, string $baseName): bool
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            return false;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return false;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $saved = imagewebp($image, null, 82);
        $webpData = ob_get_clean();
        imagedestroy($image);

        if (! $saved || $webpData === false) {
            return false;
        }

        $this->storeBytes($path, $webpData);
        $this->storeThumbnail($webpData, $directory.'/'.$baseName.'_thumb.webp');

        return true;
    }

    protected function storeThumbnail(string $imageData, string $thumbPath, int $maxWidth = 400): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth) {
            imagedestroy($image);

            return;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($newWidth / $width));
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagewebp($thumb, null, 80);
        $thumbData = ob_get_clean();
        imagedestroy($thumb);

        if ($thumbData) {
            $this->storeBytes($thumbPath, $thumbData);
        }
    }

    protected function storeBytes(string $relativePath, string $contents): void
    {
        $relativePath = ltrim($relativePath, '/');
        Storage::disk('public')->makeDirectory(dirname($relativePath));

        if (! Storage::disk('public')->put($relativePath, $contents)) {
            throw new RuntimeException('Could not save image to storage/app/public.');
        }

        $this->mirrorToPublicPath($relativePath, $contents);

        if (! Storage::disk('public')->exists($relativePath)) {
            throw new RuntimeException('Image file missing after save.');
        }
    }

    /** cPanel Plan B: also write under public_html/storage for direct web access. */
    protected function mirrorToPublicPath(string $relativePath, string $contents): void
    {
        $target = public_path('storage/'.$relativePath);
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create public storage directory.');
        }

        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException('Could not mirror image to public storage.');
        }
    }
}
