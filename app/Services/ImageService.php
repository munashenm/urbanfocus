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
            if ($this->storeAsWebp($file, $webpPath)) {
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

    protected function storeAsWebp(UploadedFile $file, string $path): bool
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

        return true;
    }
}
