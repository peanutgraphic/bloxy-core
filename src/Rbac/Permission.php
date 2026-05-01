<?php

declare(strict_types=1);

namespace Bloxy\Core\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'bloxy_permissions';
    protected $guarded = [];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'bloxy_role_permissions',
            'permission_id',
            'role_id'
        );
    }
}
