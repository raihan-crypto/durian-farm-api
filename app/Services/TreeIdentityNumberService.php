<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\Garden;
use App\Models\Tree;

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

        // 1. Block Code (e.g. 06.05.01)
        $blockCode = $block?->block_code;
        if (empty($blockCode) && $block !== null) {
            $blockCode = app(TerritoryCodeService::class)->blockCodeFor($block);
        }
        if (empty($blockCode)) {
            $villageCode = $village?->village_code ?: '00.00';
            $blockCode = $villageCode.'.01';
        }

        // 2. Garden Code (2 digits, e.g. 02)
        $gardenCodeRaw = $garden?->garden_code;
        if (empty($gardenCodeRaw) && $garden !== null) {
            $gardenCodeRaw = app(TerritoryCodeService::class)->gardenCodeFor($garden);
        }
        $gardenDigits = preg_replace('/\D/', '', (string) $gardenCodeRaw);
        $gardenCode = sprintf('%02d', ! empty($gardenDigits) ? (int) $gardenDigits : 1);

        // 3. Variety Code
        $varietyCode = 'DUR';
        if ($variety !== null) {
            $codeCandidate = strtoupper(trim((string) ($variety->code ?: $variety->name)));
            $cleaned = preg_replace('/[^A-Z0-9]/', '', $codeCandidate);
            if (! empty($cleaned)) {
                $varietyCode = substr($cleaned, 0, 4);
            }
        }

        $prefix = sprintf('%s.%s-%s-', $blockCode, $gardenCode, $varietyCode);

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
