<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Central audit trail writer (master spec section 33). Every controller
 * that performs a security-relevant or financial/academic write calls
 * AuditLogger::log() so there is one consistent record shape across the
 * whole application.
 */
class AuditLogger
{
    public static function log(string $action, string $module, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        $user = Auth::user();

        $entry = AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'system',
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'ip_address' => request()->ip(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);

        Log::channel('audit')->info(sprintf(
            '[%s] %s performed %s on %s #%s',
            now()->toDateTimeString(),
            $user?->email ?? 'system',
            $action,
            $module,
            $recordId ?? '-'
        ));

        return $entry;
    }
}
