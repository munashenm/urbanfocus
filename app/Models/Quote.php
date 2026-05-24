<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'type', 'name', 'company', 'email', 'phone',
        'message', 'file_path', 'product_id', 'status', 'admin_notes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'rfq' => 'RFQ Upload',
            'bulk' => 'Bulk Order',
            'source' => 'Product Sourcing',
            'procurement' => 'Corporate Procurement',
            default => 'Quote Request',
        };
    }
}
