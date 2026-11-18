<?php

namespace App\Services;

use App\Models\Block;
use App\Models\BlockFarmer;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\User;
use Illuminate\Support\Carbon;

class TerritoryScopeService
{
    /**
     * Roles that have unrestricted visibility over all territories.
     *
     * @var array<int, string>
     */
    private const UNRESTRICTED_ROLES = [
        'super_admin',
        'admin',
        'plantation_manager',
    ];

    /**
     * Determine whether the user's territory visibility must be scoped to their farmer assignments.
     *
     * Users without an unrestricted role are always scoped (deny-by-default). Users that have
     * no farmer profile (e.g. owner-only accounts, which cannot yet be linked to an owner record)
     * therefore see no territory data until a proper link exists.
     */
    public function isScoped(User $user): bool
    {
        $user->loadMissing('roles');

        $roleNames = $user->roles->pluck('name')->all();

        foreach (self::UNRESTRICTED_ROLES as $role) {
            if (in_array($role, $roleNames, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the active block IDs directly assigned to a farmer.
     *
     * @return array<int, string>
     */
    public function getActiveBlockIdsForFarmer(Farmer $farmer): array
    {
        $today = Carbon::today()->toDateString();

        return BlockFarmer::query()
            ->where('farmer_id', $farmer->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->pluck('block_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the active garden IDs directly assigned to a farmer.
     *
     * @return array<int, string>
     */
    public function getActiveDirectGardenIdsForFarmer(Farmer $farmer): array
    {
        $today = Carbon::today()->toDateString();

        return GardenFarmer::query()
            ->where('farmer_id', $farmer->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->pluck('garden_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get all garden IDs accessible by the user.
     * Returns null when the user is unrestricted.
     *
     * @return array<int, string>|null
     */
    public function getAccessibleGardenIds(User $user): ?array
    {
        if (! $this->isScoped($user)) {
            return null;
        }

        $farmer = $user->farmerProfile;

        if (! $farmer) {
            return [];
        }

        $directGardenIds = $this->getActiveDirectGardenIdsForFarmer($farmer);
        $assignedBlockIds = $this->getActiveBlockIdsForFarmer($farmer);

        $blockGardenIds = empty($assignedBlockIds)
            ? []
            : Garden::query()->whereIn('block_id', $assignedBlockIds)->pluck('id')->all();

        return array_values(array_unique(array_merge($directGardenIds, $blockGardenIds)));
    }

    /**
     * Get all block IDs accessible by the user.
     * Includes directly assigned blocks and parent blocks of directly assigned gardens.
     * Returns null when the user is unrestricted.
     *
     * @return array<int, string>|null
     */
    public function getAccessibleBlockIds(User $user): ?array
    {
        if (! $this->isScoped($user)) {
            return null;
        }

        $farmer = $user->farmerProfile;

        if (! $farmer) {
            return [];
        }

        $assignedBlockIds = $this->getActiveBlockIdsForFarmer($farmer);
        $directGardenIds = $this->getActiveDirectGardenIdsForFarmer($farmer);

        $parentBlockIds = empty($directGardenIds)
            ? []
            : Garden::query()->whereIn('id', $directGardenIds)->pluck('block_id')->all();

        return array_values(array_unique(array_merge($assignedBlockIds, $parentBlockIds)));
    }

    /**
     * Get all village IDs accessible by the user.
     * Returns null when the user is unrestricted.
     *
     * @return array<int, string>|null
     */
    public function getAccessibleVillageIds(User $user): ?array
    {
        $blockIds = $this->getAccessibleBlockIds($user);

        if ($blockIds === null) {
            return null;
        }

        if (empty($blockIds)) {
            return [];
        }

        return Block::query()
            ->whereIn('id', $blockIds)
            ->pluck('village_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check whether the user can access a specific garden.
     */
    public function canAccessGarden(User $user, string $gardenId): bool
    {
        $accessibleGardenIds = $this->getAccessibleGardenIds($user);

        if ($accessibleGardenIds === null) {
            return true;
        }

        return in_array($gardenId, $accessibleGardenIds, true);
    }

    /**
     * Check whether the user can access a specific block.
     */
    public function canAccessBlock(User $user, string $blockId): bool
    {
        $accessibleBlockIds = $this->getAccessibleBlockIds($user);

        if ($accessibleBlockIds === null) {
            return true;
        }

        return in_array($blockId, $accessibleBlockIds, true);
    }

    /**
     * Check whether the user can access a specific village.
     */
    public function canAccessVillage(User $user, string $villageId): bool
    {
        $accessibleVillageIds = $this->getAccessibleVillageIds($user);

        if ($accessibleVillageIds === null) {
            return true;
        }

        return in_array($villageId, $accessibleVillageIds, true);
    }
}
