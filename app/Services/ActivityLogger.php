<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Record an audit log entry for an administrative or financial action.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function log(
        string $module,
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $properties = null,
    ): void {
        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'module' => $module,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
