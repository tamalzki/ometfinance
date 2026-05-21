<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Adds a lightweight audit trail to any Eloquent model.
 *
 * Records every create/update/delete with:
 *   - the authenticated user_id (or null if a console job)
 *   - old + new values for changed columns (encrypted fields are stored as
 *     the encrypted ciphertext, not plaintext, because that's what came off
 *     the model's $original — we never decrypt for the audit row).
 *   - request IP + user-agent when the change came from an HTTP request.
 *
 * Sensitive columns can be excluded from the audit payload by declaring a
 * `$auditExclude` array on the model.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            self::writeAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }
            $old = collect($changes)
                ->mapWithKeys(fn ($_v, $k) => [$k => $model->getOriginal($k)])
                ->all();
            self::writeAudit($model, 'updated', $old, $changes);
        });

        static::deleted(function (Model $model): void {
            self::writeAudit($model, 'deleted', $model->getOriginal(), []);
        });
    }

    /**
     * @param  array<string,mixed>  $old
     * @param  array<string,mixed>  $new
     */
    private static function writeAudit(Model $model, string $event, array $old, array $new): void
    {
        $exclude = property_exists($model, 'auditExclude')
            ? (array) $model->auditExclude
            : ['updated_at', 'created_at'];

        $old = array_diff_key($old, array_flip($exclude));
        $new = array_diff_key($new, array_flip($exclude));

        $request = request();

        AuditLog::create([
            'user_id'        => optional(auth()->user())->id,
            'event'          => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => (int) $model->getKey(),
            'old_values'     => empty($old) ? null : $old,
            'new_values'     => empty($new) ? null : $new,
            'ip_address'     => optional($request)->ip(),
            'user_agent'     => optional($request)->userAgent() ? substr($request->userAgent(), 0, 512) : null,
            'created_at'     => Carbon::now(),
        ]);
    }
}
