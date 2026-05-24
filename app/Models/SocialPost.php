<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class SocialPost extends Model
{
    protected $fillable = [
        'postable_type', 'postable_id', 'platform', 'status', 'message',
        'image_url', 'link_url', 'external_id', 'error_message', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markPosted(string $externalId = null): void
    {
        $this->update([
            'status' => 'posted',
            'external_id' => $externalId,
            'error_message' => null,
            'posted_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => Str::limit($error, 500),
        ]);
    }
}
