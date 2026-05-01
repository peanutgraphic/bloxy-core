<?php

declare(strict_types=1);

namespace Bloxy\Core\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'bloxy_roles';
    protected $guarded = [];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'bloxy_role_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function assignments()
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
