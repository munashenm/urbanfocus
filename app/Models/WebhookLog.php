<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WebhookLog extends Model
{
    protected $fillable = [
        'event', 'target_type', 'target_id', 'target_label', 'destination',
        'webhook_url', 'platforms', 'payload', 'status', 'http_status',
        'response', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'payload' => 'array',
        ];
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function markSuccess(int $httpStatus, ?string $response = null): void
    {
        $this->update([
            'status' => 'success',
            'http_status' => $httpStatus,
            'response' => $response ? Str::limit($response, 1000) : null,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $error, ?int $httpStatus = null, ?string $response = null): void
    {
        $this->update([
            'status' => 'failed',
            'http_status' => $httpStatus,
            'response' => $response ? Str::limit($response, 1000) : null,
            'error_message' => Str::limit($error, 1000),
        ]);
    }

    public function platformList(): array
    {
        return is_array($this->platforms) ? $this->platforms : [];
    }
}
