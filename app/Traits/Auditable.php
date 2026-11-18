<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Attributes that must never be persisted to audit_logs.
     *
     * @var array<int, string>
     */
    private static array $auditExcludedAttributes = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::writeAuditLog($model, 'created', null, static::filterAuditAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = static::filterAuditAttributes($model->getChanges());
            if (empty($changes)) {
                return;
            }

            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            static::writeAuditLog($model, 'updated', $original, $changes);
        });

        static::deleted(function (Model $model) {
            static::writeAuditLog($model, 'deleted', static::filterAuditAttributes($model->getOriginal()), null);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function filterAuditAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::$auditExcludedAttributes));
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected static function writeAuditLog(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $request = app()->bound('request') ? request() : null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => (string) $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
