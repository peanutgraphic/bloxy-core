<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Auto-audit a model on create/update/delete events.
 *
 * Set `protected $auditReads = true` on the host model to also enable the
 * `recordRead($context)` helper for opt-in read-access logging. (No global
 * retrieve hook — too noisy. Apps record reads explicitly when meaningful.)
 */
trait HasAudit
{
    public static function bootHasAudit(): void
    {
        static::created(function (Model $model) {
            self::writeAuditEntry($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            $before = [];
            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getOriginal($key);
            }
            self::writeAuditEntry($model, 'updated', $before, $changes);
        });

        static::deleted(function (Model $model) {
            self::writeAuditEntry($model, 'deleted', $model->getAttributes(), null);
        });
    }

    /**
     * Record an explicit read-access event.
     *
     * Apps call this from controllers / services when reading data that
     * deserves audit (e.g., flagged-PHI tables). The trait does not hook
     * Eloquent retrieve events automatically — that would log every row of
     * every list, which is noise.
     */
    public function recordRead(array $context = []): AuditLog
    {
        return self::writeAuditEntry($this, 'read', null, null, $context);
    }

    private static function writeAuditEntry(
        Model $model,
        string $action,
        ?array $before,
        ?array $after,
        array $extraMeta = [],
    ): AuditLog {
        $actor = self::resolveActor();
        $request = self::resolveRequest();

        return AuditLog::create([
            'happened_at' => Carbon::now(),
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'action' => $action,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey() === null ? null : (string) $model->getKey(),
            'changes' => ($before === null && $after === null) ? null : [
                'before' => $before,
                'after' => $after,
            ],
            'ip_address' => $request['ip'],
            'user_agent' => $request['user_agent'],
            'request_id' => $request['request_id'],
            'meta' => $extraMeta === [] ? null : $extraMeta,
        ]);
    }

    /** @return array{type: ?string, id: ?string} */
    private static function resolveActor(): array
    {
        if (! function_exists('auth')) {
            return ['type' => null, 'id' => null];
        }

        $user = auth()->user();
        if ($user === null) {
            return ['type' => null, 'id' => null];
        }

        return [
            'type' => method_exists($user, 'getMorphClass') ? $user->getMorphClass() : 'user',
            'id' => (string) $user->getKey(),
        ];
    }

    /** @return array{ip: ?string, user_agent: ?string, request_id: ?string} */
    private static function resolveRequest(): array
    {
        if (! function_exists('request')) {
            return ['ip' => null, 'user_agent' => null, 'request_id' => null];
        }

        try {
            $request = request();
        } catch (\Throwable) {
            return ['ip' => null, 'user_agent' => null, 'request_id' => null];
        }

        if ($request === null) {
            return ['ip' => null, 'user_agent' => null, 'request_id' => null];
        }

        $requestId = $request->attributes->get('audit.request_id');

        return [
            'ip' => method_exists($request, 'ip') ? $request->ip() : null,
            'user_agent' => method_exists($request, 'userAgent') ? $request->userAgent() : null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ];
    }
}
