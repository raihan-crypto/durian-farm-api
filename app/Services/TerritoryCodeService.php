<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Garden;
use App\Models\Tree;
use App\Models\Village;
use App\Support\BanyumasRegionData;

/**
 * Central generator for hierarchical territory codes.
 *
 * Format:
 * - Desa  : [KodeKecamatan].[UrutDesa]                 e.g. 06.05
 * - Blok  : [KodeDesa].[UrutBlok]                       e.g. 06.05.01
 * - Kebun : [UrutKebun] (2 digit, urutan dalam blok)    e.g. 02
 * - Pohon : [KodeBlok].[KodeKebun]-[Varietas]-[Urut]    e.g. 06.05.01.02-BWR-0001
 */
class TerritoryCodeService
{
    public const SEPARATOR = '.';

    /**
     * Build the village code: district code + village order inside the district.
     */
    public function villageCodeFor(Village $village): string
    {
        $districtCode = BanyumasRegionData::getDistrictCode($village->district)
            ?? BanyumasRegionData::UNKNOWN_DISTRICT_CODE;

        $villageOrder = BanyumasRegionData::findVillageCode($village->district, $village->name);

        if ($villageOrder === null) {
            $villageOrder = $this->nextVillageOrderOutsideReference($village, $districtCode);
        }

        return $districtCode.self::SEPARATOR.$villageOrder;
    }

    /**
     * Build the block code: village code + next block order inside the village.
     */
    public function blockCodeFor(Block $block): string
    {
        $village = $block->relationLoaded('village') && $block->village?->id === $block->village_id
            ? $block->village
            : Village::withTrashed()->find($block->village_id);

        $villageCode = $village?->village_code ?: ($village ? $this->villageCodeFor($village) : '00'.self::SEPARATOR.'00');

        $usedOrders = Block::withTrashed()
            ->where('village_id', $block->village_id)
            ->when($block->exists, fn ($query) => $query->whereKeyNot($block->getKey()))
            ->pluck('block_code')
            ->map(fn (?string $code) => $this->lastSegmentAsInt($code))
            ->filter()
            ->all();

        $nextOrder = empty($usedOrders) ? 1 : max($usedOrders) + 1;

        return $villageCode.self::SEPARATOR.sprintf('%02d', $nextOrder);
    }

    /**
     * Re-prefix an existing block code with a (new) village code while keeping its order.
     */
    public function rePrefixBlockCode(Block $block, string $villageCode): string
    {
        $order = $this->lastSegmentAsInt($block->block_code) ?: 1;

        return $villageCode.self::SEPARATOR.sprintf('%02d', $order);
    }

    /**
     * Build the garden code: next 2-digit order inside the block.
     */
    public function gardenCodeFor(Garden $garden): string
    {
        $usedOrders = Garden::withTrashed()
            ->where('block_id', $garden->block_id)
            ->when($garden->exists, fn ($query) => $query->whereKeyNot($garden->getKey()))
            ->pluck('garden_code')
            ->map(fn (?string $code) => $this->lastSegmentAsInt($code))
            ->filter()
            ->all();

        $nextOrder = empty($usedOrders) ? 1 : max($usedOrders) + 1;

        return sprintf('%02d', $nextOrder);
    }

    /**
     * Propagate a village code change to its blocks (and their trees).
     */
    public function cascadeVillage(Village $village): void
    {
        Block::withTrashed()->where('village_id', $village->id)->get()->each(function (Block $block) use ($village) {
            $block->block_code = $this->rePrefixBlockCode($block, (string) $village->village_code);
            $block->saveQuietly();
            $this->refreshTreesOfBlock($block);
        });
    }

    /**
     * Regenerate the tree identity numbers of every tree inside a block.
     */
    public function refreshTreesOfBlock(Block $block): void
    {
        Garden::withTrashed()->where('block_id', $block->id)->pluck('id')->each(
            fn (string $gardenId) => $this->refreshTreesOfGarden($gardenId)
        );
    }

    /**
     * Regenerate the tree identity numbers of every tree inside a garden.
     */
    public function refreshTreesOfGarden(string $gardenId): void
    {
        $service = app(TreeIdentityNumberService::class);

        Tree::withTrashed()->where('garden_id', $gardenId)->orderBy('created_at')->get()->each(function (Tree $tree) use ($service) {
            $tree->tree_identity_number = $service->generateForTree($tree);
            $tree->saveQuietly();
        });
    }

    /**
     * Next order for a village that is not listed in the reference data of its district.
     */
    protected function nextVillageOrderOutsideReference(Village $village, string $districtCode): string
    {
        $referenceCount = count(BanyumasRegionData::getVillagesForDistrict($village->district));

        $usedOrders = Village::withTrashed()
            ->where('district', $village->district)
            ->when($village->exists, fn ($query) => $query->whereKeyNot($village->getKey()))
            ->pluck('village_code')
            ->filter(fn (?string $code) => $code !== null && str_starts_with($code, $districtCode.self::SEPARATOR))
            ->map(fn (string $code) => $this->lastSegmentAsInt($code))
            ->filter()
            ->all();

        $nextOrder = max([$referenceCount, ...$usedOrders, 0]) + 1;

        return sprintf('%02d', $nextOrder);
    }

    /**
     * Read the last numeric segment of a code ("06.05.03" => 3, "03" => 3).
     */
    public function lastSegmentAsInt(?string $code): int
    {
        if ($code === null || $code === '') {
            return 0;
        }

        $segments = explode(self::SEPARATOR, $code);
        $digits = preg_replace('/\D/', '', (string) end($segments));

        return $digits === '' ? 0 : (int) $digits;
    }
}
