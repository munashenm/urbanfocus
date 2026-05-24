<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
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

        Storage::disk('public')->put($path, $webpData);
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
            Storage::disk('public')->put($thumbPath, $thumbData);
        }
    }
}
