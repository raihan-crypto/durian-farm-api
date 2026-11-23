<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Garden;
use App\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Prevents duplicated territory data (desa, blok, kebun).
 *
 * Rules:
 * - Desa  : nama sama di kecamatan yang sama tidak boleh.
 * - Blok  : nama sama di desa yang sama tidak boleh.
 * - Kebun : nama sama di blok yang sama tidak boleh.
 * Data di Tempat Sampah (soft deleted) ikut diperiksa agar pengguna me-restore, bukan membuat ulang.
 */
class TerritoryDuplicateGuard
{
    public function findDuplicateVillage(?string $name, ?string $district, ?string $ignoreId = null): ?Village
    {
        if (blank($name) || blank($district)) {
            return null;
        }

        /** @var Village|null */
        return $this->duplicateQuery(Village::withTrashed(), $name, $ignoreId)
            ->whereRaw('LOWER(TRIM(district)) = ?', [mb_strtolower(trim($district))])
            ->first();
    }

    public function findDuplicateBlock(?string $name, ?string $villageId, ?string $ignoreId = null): ?Block
    {
        if (blank($name) || blank($villageId)) {
            return null;
        }

        /** @var Block|null */
        return $this->duplicateQuery(Block::withTrashed(), $name, $ignoreId)
            ->where('village_id', $villageId)
            ->first();
    }

    public function findDuplicateGarden(?string $name, ?string $blockId, ?string $ignoreId = null): ?Garden
    {
        if (blank($name) || blank($blockId)) {
            return null;
        }

        /** @var Garden|null */
        return $this->duplicateQuery(Garden::withTrashed(), $name, $ignoreId)
            ->where('block_id', $blockId)
            ->first();
    }

    /**
     * Human readable rejection message for a duplicate.
     */
    public function message(string $label, string $scopeLabel, Model $duplicate): string
    {
        if (method_exists($duplicate, 'trashed') && $duplicate->trashed()) {
            return "{$label} dengan nama ini sudah pernah dibuat di {$scopeLabel} yang sama dan sekarang ada di Tempat Sampah. Silakan pulihkan (restore) data tersebut, jangan membuat ulang.";
        }

        return "{$label} dengan nama ini sudah terdaftar di {$scopeLabel} yang sama. Data tidak boleh dobel.";
    }

    public function assertVillageIsUnique(Village $village): void
    {
        $duplicate = $this->findDuplicateVillage($village->name, $village->district, $village->exists ? $village->id : null);

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => $this->message('Desa', 'kecamatan', $duplicate)]);
        }
    }

    public function assertBlockIsUnique(Block $block): void
    {
        $duplicate = $this->findDuplicateBlock($block->name, $block->village_id, $block->exists ? $block->id : null);

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => $this->message('Blok', 'desa', $duplicate)]);
        }
    }

    public function assertGardenIsUnique(Garden $garden): void
    {
        $duplicate = $this->findDuplicateGarden($garden->name, $garden->block_id, $garden->exists ? $garden->id : null);

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => $this->message('Kebun', 'blok', $duplicate)]);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function duplicateQuery(Builder $query, string $name, ?string $ignoreId): Builder
    {
        return $query
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn (Builder $builder) => $builder->whereKeyNot($ignoreId));
    }
}
