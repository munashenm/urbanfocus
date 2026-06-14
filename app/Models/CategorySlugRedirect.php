<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorySlugRedirect extends Model
{
    protected $fillable = [
        'old_slug',
        'target_path',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
        ];
    }

    public static function targetForSlug(string $slug): ?string
    {
        return static::where('old_slug', $slug)->value('target_path');
    }
}
