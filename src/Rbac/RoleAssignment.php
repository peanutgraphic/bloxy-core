<?php

declare(strict_types=1);

namespace Bloxy\Core\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

class RoleAssignment extends Model
{
    protected $table = 'bloxy_role_assignments';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activation_predicate' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Enforce the (user_type, user_id, role_id, resource_type, resource_id)
        // uniqueness at the model layer. The migration declares a UNIQUE index,
        // but on SQLite (and per ANSI SQL) NULLs are treated as distinct, so
        // duplicate global grants (resource_type/resource_id NULL) would slip
        // through. This guard makes the contract portable across drivers.
        static::creating(function (self $model): void {
            $exists = static::query()
                ->where('user_type', $model->user_type)
                ->where('user_id', $model->user_id)
                ->where('role_id', $model->role_id)
                ->where(function ($q) use ($model) {
                    $model->resource_type === null
                        ? $q->whereNull('resource_type')
                        : $q->where('resource_type', $model->resource_type);
                })
                ->where(function ($q) use ($model) {
                    $model->resource_id === null
                        ? $q->whereNull('resource_id')
                        : $q->where('resource_id', $model->resource_id);
                })
                ->exists();

            if ($exists) {
                throw new QueryException(
                    static::query()->getConnection()->getName(),
                    'INSERT INTO bloxy_role_assignments',
                    [],
                    new \RuntimeException(
                        'UNIQUE constraint failed: bloxy_role_assignments_unique'
                    )
                );
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        // M1.3 ships the storage slot only. Any non-null predicate is treated
        // as "inactive" until M1.4+ plugs in a real predicate evaluator.
        return $this->activation_predicate === null;
    }
}
