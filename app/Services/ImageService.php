<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function storeProductImage(UploadedFile $file, int $productId): string
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = 'products/'.$productId.'/'.$filename;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
