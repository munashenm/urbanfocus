<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($action = $request->get('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
