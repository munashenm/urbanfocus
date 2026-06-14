<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMigrationMapping extends Model
{
    protected $fillable = [
        'old_category_id',
        'new_category_id',
        'old_slug',
        'new_slug_path',
        'match_method',
        'products_moved',
    ];

    public function oldCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'old_category_id');
    }

    public function newCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'new_category_id');
    }
}
