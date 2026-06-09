<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait LogsAdminActivity
{
    protected function audit(string $action, ?Model $subject = null, array $properties = []): void
    {
        app(AuditLogService::class)->log($action, $subject, $properties);
    }
}
