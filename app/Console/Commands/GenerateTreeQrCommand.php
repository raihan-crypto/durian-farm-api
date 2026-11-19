<?php

namespace App\Console\Commands;

use App\Models\Tree;
use App\Services\TreeQrCodeService;
use Illuminate\Console\Command;

class GenerateTreeQrCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'durian:generate-qr {--tree= : ID (UUID) atau kode pohon spesifik} {--force : Regenerasi token dan QR code baru untuk pohon yang sudah memiliki QR}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate atau regenerasi QR Code unik untuk pohon durian (SRS Bab 10)';

    /**
     * Execute the console command.
     */
    public function handle(TreeQrCodeService $qrCodeService): int
    {
        $treeQuery = Tree::query();
        $targetTree = $this->option('tree');

        if ($targetTree) {
            $treeQuery->where(function ($q) use ($targetTree) {
                $q->where('id', $targetTree)->orWhere('tree_code', $targetTree);
            });
        }

        $trees = $treeQuery->get();

        if ($trees->isEmpty()) {
            $this->warn('Tidak ada pohon yang ditemukan untuk diproses.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $rows = [];

        foreach ($trees as $tree) {
            $existing = $tree->qrCode;

            if ($existing && $force) {
                $qrCode = $qrCodeService->regenerate($existing);
                $status = 'Diregenerasi (Token Baru)';
            } elseif ($existing) {
                $qrCode = $existing;
                $status = 'Sudah Ada (Aktif)';
            } else {
                $qrCode = $qrCodeService->ensureForTree($tree);
                $status = 'Baru Dibuat';
            }

            $rows[] = [
                'Kode Pohon' => $tree->tree_code,
                'Nama' => $tree->tree_name ?? '-',
                'Public Token' => substr($qrCode->public_token, 0, 10).'...'.substr($qrCode->public_token, -6),
                'Public URL' => $qrCode->qr_code,
                'Status' => $status,
            ];
        }

        $this->table(['Kode Pohon', 'Nama', 'Public Token', 'Public URL', 'Status'], $rows);
        $this->info("Berhasil memproses QR Code untuk {$trees->count()} pohon.");

        return self::SUCCESS;
    }
}
