<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogAnalyticsSnapshot extends Model
{
    protected $fillable = ['snapshot_date', 'source', 'payload'];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'payload' => 'array',
        ];
    }
}
