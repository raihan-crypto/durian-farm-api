<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\TreeQrCode;
use Illuminate\Support\Facades\Storage;

class PublicTreePresenter
{
    /**
     * Resolve the effective list of public fields for a QR Code.
     *
     * @return array<int, string>
     */
    public function effectiveFields(TreeQrCode $qrCode): array
    {
        $available = (array) config('durian.public_tree.available_fields', []);
        $fields = $qrCode->public_fields ?? (array) config('durian.public_tree.default_fields', []);

        return array_values(array_intersect($available, $fields));
    }

    /**
     * Build the public, privacy-safe representation of a tree (SRS 10.2).
     * Owner/farmer personal data, phone numbers, accounts, exact coordinates
     * and internal IDs are never included.
     *
     * @return array<string, mixed>
     */
    public function present(TreeQrCode $qrCode): array
    {
        $qrCode->loadMissing([
            'tree.variety',
            'tree.garden.block.village',
            'tree.latestMeasurement',
        ]);

        $tree = $qrCode->tree;
        $fields = $this->effectiveFields($qrCode);
        $data = [];

        if (in_array('tree_code', $fields, true)) {
            $data['tree_code'] = $tree->tree_code;
        }

        if (in_array('tree_name', $fields, true)) {
            $data['tree_name'] = $tree->tree_name;
        }

        if (in_array('variety', $fields, true)) {
            $data['variety'] = $tree->variety ? [
                'name' => $tree->variety->name,
                'origin' => $tree->variety->origin,
                'description' => $tree->variety->description,
                'characteristics' => $tree->variety->characteristics,
            ] : null;
        }

        if (in_array('garden', $fields, true)) {
            $data['garden'] = $tree->garden ? ['name' => $tree->garden->name] : null;
        }

        if (in_array('general_location', $fields, true)) {
            $village = $tree->garden?->block?->village;
            $data['general_location'] = $village ? [
                'village' => $village->name,
                'district' => $village->district,
                'regency' => $village->regency,
                'province' => $village->province,
            ] : null;
        }

        if (in_array('planted_at', $fields, true)) {
            $data['planted_at'] = $tree->planted_at?->toDateString();
        }

        if (in_array('age', $fields, true)) {
            $months = $tree->ageInMonths();
            $data['age'] = $months === null ? null : [
                'months' => $months,
                'years' => intdiv($months, 12),
                'label' => $this->ageLabel($months),
            ];
        }

        if (in_array('status', $fields, true)) {
            $data['status'] = $tree->statusLabel();
        }

        if (in_array('latest_measurement', $fields, true)) {
            $measurement = $tree->latestMeasurement;
            $data['latest_measurement'] = $measurement ? [
                'measured_at' => $measurement->measured_at?->toDateString(),
                'diameter_cm' => $measurement->diameter_cm !== null ? (float) $measurement->diameter_cm : null,
                'height_m' => $measurement->height_m !== null ? (float) $measurement->height_m : null,
                'canopy_width_m' => $measurement->canopy_width_m !== null ? (float) $measurement->canopy_width_m : null,
            ] : null;
        }

        if (in_array('photos', $fields, true)) {
            $disk = Storage::disk((string) config('durian.uploads.disk', 'public'));

            $data['photos'] = $tree->photos()
                ->limit((int) config('durian.public_tree.max_public_photos', 6))
                ->get()
                ->map(fn (ActivityPhoto $photo) => [
                    'url' => $disk->url($photo->file_path),
                    'caption' => $photo->caption,
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * Human readable Indonesian age label.
     */
    protected function ageLabel(int $months): string
    {
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years === 0) {
            return "{$remainingMonths} bulan";
        }

        return $remainingMonths === 0
            ? "{$years} tahun"
            : "{$years} tahun {$remainingMonths} bulan";
    }
}
