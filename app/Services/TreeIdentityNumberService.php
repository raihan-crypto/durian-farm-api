<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\Tree;
use App\Support\BanyumasRegionData;

class TreeIdentityNumberService
{
    /**
     * Generate or recalculate a standardized NIK for a tree.
     * Format: [KodeDesa 2-digit]-[KodeBlok 2-digit]-[KodeKebun 2-digit]-[KodeVarietas]-[NomorUrut 4-digit]
     */
    public function generateForTree(Tree $tree): string
    {
        $garden = ($tree->isDirty('garden_id') || ! $tree->relationLoaded('garden'))
            ? Garden::with(['block.village'])->find($tree->garden_id)
            : ($tree->garden ?? Garden::with(['block.village'])->find($tree->garden_id));

        $variety = ($tree->isDirty('variety_id') || ! $tree->relationLoaded('variety'))
            ? DurianVariety::find($tree->variety_id)
            : ($tree->variety ?? DurianVariety::find($tree->variety_id));

        $block = $garden?->block;
        $village = $block?->village;

        // 1. Village Code (2 digits)
        $villageCodeRaw = $village?->village_code;
        if (empty($villageCodeRaw) && $village !== null) {
            $villageCodeRaw = BanyumasRegionData::findVillageCode($village->name, $village->district);
        }
        $villageDigits = preg_replace('/\D/', '', (string) $villageCodeRaw);
        $villageCode = sprintf('%02d', ! empty($villageDigits) ? (int) $villageDigits : 1);

        // 2. Block Code (2 digits)
        $blockCodeRaw = $block?->block_code;
        $blockDigits = preg_replace('/\D/', '', (string) $blockCodeRaw);
        $blockCode = sprintf('%02d', ! empty($blockDigits) ? (int) $blockDigits : 1);

        // 3. Garden Code (2 digits)
        $gardenCodeRaw = $garden?->garden_code;
        $gardenDigits = preg_replace('/\D/', '', (string) $gardenCodeRaw);
        $gardenCode = sprintf('%02d', ! empty($gardenDigits) ? (int) $gardenDigits : 1);

        // 4. Variety Code
        $varietyCode = 'DUR';
        if ($variety !== null) {
            $codeCandidate = strtoupper(trim((string) ($variety->code ?: $variety->name)));
            $cleaned = preg_replace('/[^A-Z0-9]/', '', $codeCandidate);
            if (! empty($cleaned)) {
                $varietyCode = substr($cleaned, 0, 4);
            }
        }

        $prefix = sprintf('%s-%s-%s-%s-', $villageCode, $blockCode, $gardenCode, $varietyCode);

        // 5. Sequence Number (4 digits)
        $existingCount = Tree::withTrashed()
            ->where('tree_identity_number', 'like', $prefix.'%')
            ->when($tree->id, fn ($q) => $q->where('id', '!=', $tree->id))
            ->count();

        $sequence = $existingCount + 1;

        // Ensure uniqueness if collision occurs
        do {
            $nik = sprintf('%s%04d', $prefix, $sequence);
            $collision = Tree::withTrashed()
                ->where('tree_identity_number', $nik)
                ->when($tree->id, fn ($q) => $q->where('id', '!=', $tree->id))
                ->exists();
            if ($collision) {
                $sequence++;
            }
        } while ($collision);

        return $nik;
    }
}
