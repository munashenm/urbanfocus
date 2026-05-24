<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order', 'max_uses',
        'used_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isValidFor(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }
        if ($this->min_order && $subtotal < (float) $this->min_order) {
            return false;
        }

        return true;
    }

    public function discountAmount(float $subtotal): float
    {
        if ($this->type === 'fixed') {
            return min((float) $this->value, $subtotal);
        }

        return round($subtotal * ((float) $this->value / 100), 2);
    }

    public function validationMessageFor(float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not valid yet.';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->min_order && $subtotal < (float) $this->min_order) {
            return 'Minimum order of R '.number_format((float) $this->min_order, 2).' required.';
        }

        return null;
    }
}
