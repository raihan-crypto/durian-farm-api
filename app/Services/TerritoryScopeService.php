<?php

namespace App\Services;

use App\Models\Block;
use App\Models\BlockFarmer;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Models\Owner;
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
    ];

    /**
     * In-memory memoization caches per request lifecycle.
     *
     * @var array<string, bool>
     */
    protected array $memoizedIsScoped = [];

    /**
     * @var array<string, array<int, string>|null>
     */
    protected array $memoizedGardenIds = [];

    /**
     * @var array<string, array<int, string>|null>
     */
    protected array $memoizedBlockIds = [];

    /**
     * @var array<string, array<int, string>|null>
     */
    protected array $memoizedVillageIds = [];

    /**
     * Determine whether the user's territory visibility must be scoped to their role assignments.
     */
    public function isScoped(User $user): bool
    {
        if (isset($this->memoizedIsScoped[$user->id])) {
            return $this->memoizedIsScoped[$user->id];
        }

        $user->loadMissing('roles');

        $roleNames = $user->roles->pluck('name')->all();

        foreach (self::UNRESTRICTED_ROLES as $role) {
            if (in_array($role, $roleNames, true)) {
                return $this->memoizedIsScoped[$user->id] = false;
            }
        }

        return $this->memoizedIsScoped[$user->id] = true;
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
     * Get the active garden IDs owned by an owner entity.
     *
     * @return array<int, string>
     */
    public function getActiveGardenIdsForOwner(Owner $owner): array
    {
        $today = Carbon::today()->toDateString();

        return GardenOwner::query()
            ->where('owner_id', $owner->id)
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
        if (array_key_exists($user->id, $this->memoizedGardenIds)) {
            return $this->memoizedGardenIds[$user->id];
        }

        if (! $this->isScoped($user)) {
            return $this->memoizedGardenIds[$user->id] = null;
        }

        $user->loadMissing('roles');
        $roleNames = $user->roles->pluck('name')->all();

        // 1. Pemerintah Desa: all gardens in blocks belonging to their village
        if (in_array('pemerintah_desa', $roleNames, true)) {
            if (empty($user->village_id)) {
                return $this->memoizedGardenIds[$user->id] = [];
            }
            $blockIds = Block::query()->where('village_id', $user->village_id)->pluck('id')->all();

            return $this->memoizedGardenIds[$user->id] = empty($blockIds)
                ? []
                : Garden::query()->whereIn('block_id', $blockIds)->pluck('id')->all();
        }

        // 2. Petani (Pengelola Kebun): gardens they own
        if (in_array('petani', $roleNames, true) || in_array('owner', $roleNames, true)) {
            $owner = $user->ownerProfile ?? Owner::query()->where('user_id', $user->id)->first();
            if (! $owner) {
                return $this->memoizedGardenIds[$user->id] = [];
            }

            return $this->memoizedGardenIds[$user->id] = $this->getActiveGardenIdsForOwner($owner);
        }

        // 3. Tenaga Kerja Kebun (or legacy farmer): gardens directly assigned or via assigned blocks
        $farmer = $user->farmerProfile;
        if (! $farmer) {
            return $this->memoizedGardenIds[$user->id] = [];
        }

        $directGardenIds = $this->getActiveDirectGardenIdsForFarmer($farmer);
        $assignedBlockIds = $this->getActiveBlockIdsForFarmer($farmer);

        $blockGardenIds = empty($assignedBlockIds)
            ? []
            : Garden::query()->whereIn('block_id', $assignedBlockIds)->pluck('id')->all();

        return $this->memoizedGardenIds[$user->id] = array_values(array_unique(array_merge($directGardenIds, $blockGardenIds)));
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
        if (array_key_exists($user->id, $this->memoizedBlockIds)) {
            return $this->memoizedBlockIds[$user->id];
        }

        if (! $this->isScoped($user)) {
            return $this->memoizedBlockIds[$user->id] = null;
        }

        $user->loadMissing('roles');
        $roleNames = $user->roles->pluck('name')->all();

        // 1. Pemerintah Desa: all blocks in their village
        if (in_array('pemerintah_desa', $roleNames, true)) {
            if (empty($user->village_id)) {
                return $this->memoizedBlockIds[$user->id] = [];
            }

            return $this->memoizedBlockIds[$user->id] = Block::query()->where('village_id', $user->village_id)->pluck('id')->all();
        }

        // 2. Petani (Pengelola Kebun): parent blocks of their owned gardens
        if (in_array('petani', $roleNames, true) || in_array('owner', $roleNames, true)) {
            $gardenIds = $this->getAccessibleGardenIds($user) ?? [];
            if (empty($gardenIds)) {
                return $this->memoizedBlockIds[$user->id] = [];
            }

            return $this->memoizedBlockIds[$user->id] = Garden::query()->whereIn('id', $gardenIds)->pluck('block_id')->unique()->values()->all();
        }

        // 3. Tenaga Kerja Kebun: assigned blocks + parent blocks of assigned gardens
        $farmer = $user->farmerProfile;
        if (! $farmer) {
            return $this->memoizedBlockIds[$user->id] = [];
        }

        $assignedBlockIds = $this->getActiveBlockIdsForFarmer($farmer);
        $directGardenIds = $this->getActiveDirectGardenIdsForFarmer($farmer);

        $parentBlockIds = empty($directGardenIds)
            ? []
            : Garden::query()->whereIn('id', $directGardenIds)->pluck('block_id')->all();

        return $this->memoizedBlockIds[$user->id] = array_values(array_unique(array_merge($assignedBlockIds, $parentBlockIds)));
    }

    /**
     * Get all village IDs accessible by the user.
     * Returns null when the user is unrestricted.
     *
     * @return array<int, string>|null
     */
    public function getAccessibleVillageIds(User $user): ?array
    {
        if (array_key_exists($user->id, $this->memoizedVillageIds)) {
            return $this->memoizedVillageIds[$user->id];
        }

        if (! $this->isScoped($user)) {
            return $this->memoizedVillageIds[$user->id] = null;
        }

        $user->loadMissing('roles');
        $roleNames = $user->roles->pluck('name')->all();

        // 1. Pemerintah Desa: their bound village
        if (in_array('pemerintah_desa', $roleNames, true)) {
            return $this->memoizedVillageIds[$user->id] = $user->village_id ? [$user->village_id] : [];
        }

        $blockIds = $this->getAccessibleBlockIds($user);

        if ($blockIds === null) {
            return $this->memoizedVillageIds[$user->id] = null;
        }

        if (empty($blockIds)) {
            return $this->memoizedVillageIds[$user->id] = [];
        }

        return $this->memoizedVillageIds[$user->id] = Block::query()
            ->whereIn('id', $blockIds)
            ->whereNotNull('village_id')
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
