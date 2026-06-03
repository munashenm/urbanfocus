<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number',
        'status',
        'customer_name',
        'customer_company',
        'customer_email',
        'customer_phone',
        'customer_vat_number',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_province',
        'billing_postal_code',
        'valid_until',
        'notes',
        'terms',
        'internal_notes',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'source_quote_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'source_quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'expired' => 'Expired',
            default => ucfirst($this->status),
        };
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && ! in_array($this->status, ['accepted', 'declined'], true);
    }
}
