<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'logo', 'website', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand', 'name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function seoTitle(): string
    {
        $configured = config("brand_seo.{$this->slug}.title");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->name.' Products | Urban Focus';
    }

    public function seoDescription(): string
    {
        $configured = config("brand_seo.{$this->slug}.description");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return seo_meta_description(
            'Shop '.$this->name.' products at Urban Focus.',
            ['type' => 'brand', 'name' => $this->name]
        );
    }

    /** @return array<string, mixed> */
    public function seoContent(): array
    {
        return config("brand_seo.{$this->slug}", []);
    }
}
