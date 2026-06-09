<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    public function log(string $action, ?Model $subject = null, array $properties = []): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }
}
