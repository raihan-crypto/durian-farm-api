<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\Garden;
use App\Models\Tree;
use App\Models\Village;
use App\Services\TerritoryCodeService;
use App\Services\TreeIdentityNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateTerritoryCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'durian:regenerate-territory-codes
                            {--dry-run : Review changes before applying them to database}
                            {--force : Apply changes without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerasi kode desa, blok, kebun, dan ID pohon ke format hierarkis baru secara aman';

    public function handle(TerritoryCodeService $territoryCodeService, TreeIdentityNumberService $treeNikService): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('=== AUDIT KODE WILAYAH & IDENTITAS POHON ===');
        $this->line($isDryRun ? 'Mode: DRY-RUN (Hanya simulasi, data tidak diubah)' : 'Mode: EKSEKUSI PENUH');

        // 1. Desa
        $villages = Village::withTrashed()->orderBy('created_at')->get();
        $villageRows = [];
        $newVillageCodes = [];

        foreach ($villages as $village) {
            $oldCode = $village->village_code ?: '-';
            $newCode = $territoryCodeService->villageCodeFor($village);
            $newVillageCodes[$village->id] = $newCode;

            $villageRows[] = [
                'ID' => substr($village->id, 0, 8).'...',
                'Nama Desa' => $village->name,
                'Kecamatan' => $village->district,
                'Kode Lama' => $oldCode,
                'Kode Baru' => $newCode,
                'Status' => $oldCode === $newCode ? 'Sama' : 'Perlu Update',
            ];
        }

        $this->newLine();
        $this->info('1. Ringkasan Kode Desa:');
        $this->table(['ID', 'Nama Desa', 'Kecamatan', 'Kode Lama', 'Kode Baru', 'Status'], $villageRows);

        // 2. Blok
        $blocks = Block::withTrashed()->orderBy('created_at')->get();
        $blockRows = [];
        $newBlockCodes = [];
        $villageBlockOrder = [];

        foreach ($blocks as $block) {
            $villageId = $block->village_id;
            $villageCode = $newVillageCodes[$villageId] ?? '00.00';
            $villageBlockOrder[$villageId] = ($villageBlockOrder[$villageId] ?? 0) + 1;
            $newBlockCode = sprintf('%s.%02d', $villageCode, $villageBlockOrder[$villageId]);
            $newBlockCodes[$block->id] = $newBlockCode;

            $oldCode = $block->block_code ?: '-';
            $blockRows[] = [
                'ID' => substr($block->id, 0, 8).'...',
                'Nama Blok' => $block->name,
                'Desa' => $block->village?->name ?? '-',
                'Kode Lama' => $oldCode,
                'Kode Baru' => $newBlockCode,
                'Status' => $oldCode === $newBlockCode ? 'Sama' : 'Perlu Update',
            ];
        }

        $this->newLine();
        $this->info('2. Ringkasan Kode Blok:');
        $this->table(['ID', 'Nama Blok', 'Desa', 'Kode Lama', 'Kode Baru', 'Status'], $blockRows);

        // 3. Kebun
        $gardens = Garden::withTrashed()->orderBy('created_at')->get();
        $gardenRows = [];
        $newGardenCodes = [];
        $blockGardenOrder = [];

        foreach ($gardens as $garden) {
            $blockId = $garden->block_id;
            $blockGardenOrder[$blockId] = ($blockGardenOrder[$blockId] ?? 0) + 1;
            $newGardenCode = sprintf('%02d', $blockGardenOrder[$blockId]);
            $newGardenCodes[$garden->id] = $newGardenCode;

            $oldCode = $garden->garden_code ?: '-';
            $gardenRows[] = [
                'ID' => substr($garden->id, 0, 8).'...',
                'Nama Kebun' => $garden->name,
                'Blok' => $garden->block?->name ?? '-',
                'Kode Lama' => $oldCode,
                'Kode Baru' => $newGardenCode,
                'Status' => $oldCode === $newGardenCode ? 'Sama' : 'Perlu Update',
            ];
        }

        $this->newLine();
        $this->info('3. Ringkasan Kode Kebun:');
        $this->table(['ID', 'Nama Kebun', 'Blok', 'Kode Lama', 'Kode Baru', 'Status'], $gardenRows);

        // 4. Pohon
        $trees = Tree::withTrashed()->with(['garden.block.village', 'variety'])->orderBy('created_at')->get();
        $treeRows = [];
        $newTreeNiks = [];

        foreach ($trees as $tree) {
            $garden = $tree->garden;
            $block = $garden?->block;
            $blockCode = $newBlockCodes[$block?->id] ?? ($block?->block_code ?: '00.00.01');
            $gardenCode = $newGardenCodes[$garden?->id] ?? ($garden?->garden_code ?: '01');

            $varietyCode = 'DUR';
            if ($tree->variety) {
                $codeCandidate = strtoupper(trim((string) ($tree->variety->code ?: $tree->variety->name)));
                $cleaned = preg_replace('/[^A-Z0-9]/', '', $codeCandidate);
                if (! empty($cleaned)) {
                    $varietyCode = substr($cleaned, 0, 4);
                }
            }

            $prefix = sprintf('%s.%s-%s-', $blockCode, $gardenCode, $varietyCode);
            $newTreeNiks[$tree->id] = $prefix;

            $oldNik = $tree->tree_identity_number ?: '-';
            $treeRows[] = [
                'Kode Pohon' => $tree->tree_code,
                'Varietas' => $tree->variety?->name ?? '-',
                'Kebun' => $garden?->name ?? '-',
                'NIK Lama' => $oldNik,
                'Prefix Baru' => $prefix.'XXXX',
            ];
        }

        $this->newLine();
        $this->info('4. Ringkasan ID / NIK Pohon (Total '.count($trees).' pohon):');
        $this->table(['Kode Pohon', 'Varietas', 'Kebun', 'NIK Lama', 'Format Baru'], array_slice($treeRows, 0, 15));
        if (count($treeRows) > 15) {
            $this->line('... dan '.(count($treeRows) - 15).' pohon lainnya.');
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('Selesai simulasi Dry-Run. Tidak ada perubahan yang disimpan ke database.');
            $this->line('Jalankan tanpa flag --dry-run untuk menerapkan perubahan secara permanen.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Apakah Anda yakin ingin menerapkan regenerasi kode ini ke database?')) {
            $this->info('Dibatalkan oleh pengguna.');

            return self::SUCCESS;
        }

        // Apply changes within transaction
        DB::transaction(function () use ($villages, $newVillageCodes, $blocks, $newBlockCodes, $gardens, $newGardenCodes, $trees, $treeNikService) {
            // Update villages
            foreach ($villages as $village) {
                if (isset($newVillageCodes[$village->id])) {
                    $village->village_code = $newVillageCodes[$village->id];
                    $village->saveQuietly();
                }
            }

            // Update blocks
            foreach ($blocks as $block) {
                if (isset($newBlockCodes[$block->id])) {
                    $block->block_code = $newBlockCodes[$block->id];
                    $block->saveQuietly();
                }
            }

            // Update gardens
            foreach ($gardens as $garden) {
                if (isset($newGardenCodes[$garden->id])) {
                    $garden->garden_code = $newGardenCodes[$garden->id];
                    $garden->saveQuietly();
                }
            }

            // Update trees
            foreach ($trees as $tree) {
                $tree->refresh();
                $tree->tree_identity_number = $treeNikService->generateForTree($tree);
                $tree->saveQuietly();
            }
        });

        $this->newLine();
        $this->info('BERHASIL: Seluruh kode wilayah (Desa, Blok, Kebun) dan NIK Pohon telah diperbarui ke format baru!');

        return self::SUCCESS;
    }
}
