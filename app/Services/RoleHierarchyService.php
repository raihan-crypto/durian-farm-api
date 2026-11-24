<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RoleHierarchyService
{
    /**
     * Roles that can only be assigned, revoked, or modified by a super_admin.
     *
     * @var array<int, string>
     */
    public const PROTECTED_ROLES = [
        'super_admin',
    ];

    public function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('roles');

        return $user->hasRole('super_admin');
    }

    public function isProtectedRoleName(string $roleName): bool
    {
        return in_array($roleName, self::PROTECTED_ROLES, true);
    }

    /**
     * @param  array<int, string>  $roleIds
     */
    public function containsProtectedRoleIds(array $roleIds): bool
    {
        if ($roleIds === []) {
            return false;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->whereIn('name', self::PROTECTED_ROLES)
            ->exists();
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    public function containsProtectedRoleNames(array $roleNames): bool
    {
        foreach ($roleNames as $roleName) {
            if ($this->isProtectedRoleName((string) $roleName)) {
                return true;
            }
        }

        return false;
    }

    public function canModifyTargetUser(User $actor, User $target): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        $target->loadMissing('roles');

        return ! $target->hasRole('super_admin');
    }

    /**
     * Determine whether $actor may assign the given roles to $target (or to a new user when $target is null).
     *
     * @param  array<int, string>  $roleIds
     * @param  array<int, string>  $roleNames
     */
    public function canAssignRoles(User $actor, ?User $target = null, array $roleIds = [], array $roleNames = []): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        if ($target !== null) {
            if (! $this->canModifyTargetUser($actor, $target)) {
                return false;
            }

            if ($actor->id === $target->id) {
                return false;
            }
        }

        if ($this->containsProtectedRoleNames($roleNames) || $this->containsProtectedRoleIds($roleIds)) {
            return false;
        }

        return true;
    }

    /**
     * Scope a Role query so non-super_admin users never see protected roles as assignable options.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeAssignableRoles(Builder $query, ?User $actor): Builder
    {
        if (! $this->isSuperAdmin($actor)) {
            $query->whereNotIn('name', self::PROTECTED_ROLES);
        }

        return $query;
    }
}
