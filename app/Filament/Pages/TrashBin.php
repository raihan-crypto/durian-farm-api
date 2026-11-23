<?php

namespace App\Filament\Pages;

use App\Models\ActivityPhoto;
use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\Owner;
use App\Models\ProductCategory;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class TrashBin extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem & Audit';

    protected static ?string $navigationLabel = 'Pusat Data Terhapus';

    protected static ?string $title = 'Pusat Data Terhapus (Trash Bin)';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.trash-bin';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    /**
     * Get summary statistics of soft-deleted records.
     *
     * @return array<string, int>
     */
    public function getSummaryProperty(): array
    {
        return [
            'users' => User::onlyTrashed()->count(),
            'villages' => Village::onlyTrashed()->count(),
            'blocks' => Block::onlyTrashed()->count(),
            'gardens' => Garden::onlyTrashed()->count(),
            'trees' => Tree::onlyTrashed()->count(),
            'varieties' => DurianVariety::onlyTrashed()->count(),
            'owners' => Owner::onlyTrashed()->count(),
            'farmers' => Farmer::onlyTrashed()->count(),
            'products' => AgriculturalProduct::onlyTrashed()->count(),
            'categories' => ProductCategory::onlyTrashed()->count(),
            'photos' => ActivityPhoto::onlyTrashed()->count(),
        ];
    }

    /**
     * Get recent soft deleted items across models.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentDeletedItemsProperty(): array
    {
        $items = [];

        $models = [
            'Desa' => Village::class,
            'Blok' => Block::class,
            'Kebun' => Garden::class,
            'Pohon Durian' => Tree::class,
            'Varietas' => DurianVariety::class,
            'Pengguna' => User::class,
            'Pemilik' => Owner::class,
            'Tenaga Kerja' => Farmer::class,
            'Produk' => AgriculturalProduct::class,
        ];

        foreach ($models as $label => $class) {
            $records = $class::onlyTrashed()->latest('deleted_at')->limit(5)->get();
            foreach ($records as $r) {
                $daysLeft = 30 - (int) Carbon::parse($r->deleted_at)->diffInDays(now());
                $name = $r->name ?? $r->tree_code ?? $r->tree_identity_number ?? $r->id;

                $items[] = [
                    'class' => $class,
                    'type' => $label,
                    'id' => $r->id,
                    'name' => $name,
                    'deleted_at' => $r->deleted_at?->format('d/m/Y H:i'),
                    'days_left' => max(0, $daysLeft),
                ];
            }
        }

        usort($items, fn ($a, $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return array_slice($items, 0, 20);
    }

    /**
     * Restore a soft-deleted record (Putback).
     */
    public function restoreRecord(string $modelClass, string $id): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $record = $modelClass::onlyTrashed()->find($id);
        if ($record) {
            $record->restore();
            Notification::make()->title("Data {$record->name} berhasil dipulihkan (putback)")->success()->send();
        }
    }

    /**
     * Permanently delete a record.
     */
    public function forceDeleteRecord(string $modelClass, string $id): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        try {
            $record = $modelClass::onlyTrashed()->find($id);
            if ($record) {
                $record->forceDelete();
                Notification::make()->title('Data telah dihapus permanen')->warning()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Hapus Permanen')
                ->body('Data tidak dapat dihapus permanen karena masih memiliki relasi data lain yang terikat.')
                ->danger()
                ->send();
        }
    }

    /**
     * Run manual pruning of records deleted > 30 days ago.
     */
    public function runPruneNow(): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            return;
        }

        Artisan::call('durian:prune-soft-deleted');
        Notification::make()->title('Pembersihan otomatis data > 30 hari selesai dijalankan')->success()->send();
    }
}
