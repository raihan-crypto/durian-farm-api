<?php

namespace App\Filament\Pages;

use App\Models\ActivityPhoto;
use App\Models\AgriculturalProduct;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\Owner;
use App\Models\ProductCategory;
use App\Models\Tree;
use App\Models\User;
use App\Models\Village;
use App\Services\PhotoStorageService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
     * Models allowed to be restored or permanently deleted via the Trash Bin.
     *
     * @var array<int, class-string<Model>>
     */
    public const ALLOWED_MODELS = [
        Village::class,
        Block::class,
        Garden::class,
        Tree::class,
        DurianVariety::class,
        User::class,
        Owner::class,
        Farmer::class,
        AgriculturalProduct::class,
        ProductCategory::class,
        ActivityPhoto::class,
    ];

    /**
     * Get dependency descriptions that prevent safe permanent deletion.
     *
     * @return array<int, string>
     */
    public function getActiveDependencyDescriptions(Model $record): array
    {
        $deps = [];

        if ($record instanceof Village) {
            $count = $record->blocks()->withTrashed()->count();
            if ($count > 0) {
                $deps[] = "{$count} blok";
            }
        } elseif ($record instanceof Block) {
            $count = $record->gardens()->withTrashed()->count();
            if ($count > 0) {
                $deps[] = "{$count} kebun";
            }
        } elseif ($record instanceof Garden) {
            $treesCount = $record->trees()->withTrashed()->count();
            if ($treesCount > 0) {
                $deps[] = "{$treesCount} pohon durian";
            }
            $seasonsCount = $record->harvestSeasons()->withTrashed()->count();
            if ($seasonsCount > 0) {
                $deps[] = "{$seasonsCount} musim panen";
            }
            $sessionsCount = $record->harvestSessions()->withTrashed()->count();
            if ($sessionsCount > 0) {
                $deps[] = "{$sessionsCount} sesi panen";
            }
            $recordsCount = $record->harvestRecords()->withTrashed()->count();
            if ($recordsCount > 0) {
                $deps[] = "{$recordsCount} catatan panen";
            }
            $areasCount = $record->monitoringAreas()->count();
            if ($areasCount > 0) {
                $deps[] = "{$areasCount} area monitoring";
            }
            $ownersCount = $record->gardenOwners()->count();
            if ($ownersCount > 0) {
                $deps[] = "{$ownersCount} kepemilikan kebun";
            }
            $farmersCount = $record->gardenFarmers()->count();
            if ($farmersCount > 0) {
                $deps[] = "{$farmersCount} penugasan petani";
            }
        } elseif ($record instanceof Tree) {
            $measurementsCount = $record->measurements()->withTrashed()->count();
            if ($measurementsCount > 0) {
                $deps[] = "{$measurementsCount} pengukuran pohon";
            }
            $harvestCount = $record->harvestRecords()->withTrashed()->count();
            if ($harvestCount > 0) {
                $deps[] = "{$harvestCount} catatan panen";
            }
            $fertilizationCount = $record->fertilizationRecords()->withTrashed()->count();
            if ($fertilizationCount > 0) {
                $deps[] = "{$fertilizationCount} catatan pemupukan";
            }
            $sprayingCount = $record->sprayingRecords()->withTrashed()->count();
            if ($sprayingCount > 0) {
                $deps[] = "{$sprayingCount} catatan penyemprotan";
            }
            $floweringCount = $record->floweringRecords()->withTrashed()->count();
            if ($floweringCount > 0) {
                $deps[] = "{$floweringCount} catatan pembungaan";
            }
            $fruitCount = $record->fruitDevelopmentRecords()->withTrashed()->count();
            if ($fruitCount > 0) {
                $deps[] = "{$fruitCount} catatan perkembangan buah";
            }
            $photosCount = $record->photos()->withTrashed()->count();
            if ($photosCount > 0) {
                $deps[] = "{$photosCount} foto pohon";
            }
        } elseif ($record instanceof AgriculturalProduct) {
            $fertCount = $record->fertilizationItems()->withTrashed()->count();
            if ($fertCount > 0) {
                $deps[] = "{$fertCount} item pemupukan";
            }
            $sprayCount = $record->sprayingItems()->withTrashed()->count();
            if ($sprayCount > 0) {
                $deps[] = "{$sprayCount} item penyemprotan";
            }
        } elseif ($record instanceof ProductCategory) {
            $prodCount = $record->products()->withTrashed()->count();
            if ($prodCount > 0) {
                $deps[] = "{$prodCount} produk pertanian";
            }
        } elseif ($record instanceof DurianVariety) {
            $treesCount = $record->trees()->withTrashed()->count();
            if ($treesCount > 0) {
                $deps[] = "{$treesCount} pohon durian";
            }
        } elseif ($record instanceof Farmer) {
            $gardenCount = $record->gardenFarmers()->count();
            if ($gardenCount > 0) {
                $deps[] = "{$gardenCount} penugasan kebun";
            }
            $blockCount = $record->blockFarmers()->count();
            if ($blockCount > 0) {
                $deps[] = "{$blockCount} penugasan blok";
            }
        } elseif ($record instanceof Owner) {
            $gardenCount = $record->gardenOwners()->count();
            if ($gardenCount > 0) {
                $deps[] = "{$gardenCount} kepemilikan kebun";
            }
        }

        return $deps;
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

        if (! in_array($modelClass, self::ALLOWED_MODELS, true)) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Model tidak diizinkan untuk dipulihkan.')
                ->danger()
                ->send();

            return;
        }

        $record = $modelClass::onlyTrashed()->find($id);
        if ($record) {
            DB::transaction(function () use ($record): void {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'restored',
                    'table_name' => $record->getTable(),
                    'record_id' => (string) $record->getKey(),
                    'old_values' => ['deleted_at' => (string) $record->deleted_at],
                    'new_values' => [
                        'restored_by' => auth()->user()?->email,
                        'source' => 'filament_trash_bin',
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $record->restore();
            });

            $name = $record->name ?? $record->tree_code ?? $record->tree_identity_number ?? $record->id;
            Notification::make()->title("Data {$name} berhasil dipulihkan (putback)")->success()->send();
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

        if (! in_array($modelClass, self::ALLOWED_MODELS, true)) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Model tidak diizinkan untuk dihapus permanen.')
                ->danger()
                ->send();

            return;
        }

        $record = $modelClass::onlyTrashed()->find($id);
        if (! $record) {
            Notification::make()->title('Data tidak ditemukan')->warning()->send();

            return;
        }

        $dependencies = $this->getActiveDependencyDescriptions($record);
        if (! empty($dependencies)) {
            $depsList = implode(', ', $dependencies);
            Notification::make()
                ->title('Gagal Hapus Permanen')
                ->body("Data tidak dapat dihapus permanen karena masih memiliki data terhubung ({$depsList}). Hapus atau pindahkan data terhubung terlebih dahulu.")
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            $mediaPathsToDelete = [];
            if ($record instanceof ActivityPhoto) {
                if ($record->file_path) {
                    $mediaPathsToDelete[] = $record->file_path;
                }
                if ($record->thumbnail_path) {
                    $mediaPathsToDelete[] = $record->thumbnail_path;
                }
            }

            DB::transaction(function () use ($record): void {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'force_deleted',
                    'table_name' => $record->getTable(),
                    'record_id' => (string) $record->getKey(),
                    'old_values' => $record->getAttributes(),
                    'new_values' => [
                        'force_deleted_by' => auth()->user()?->email,
                        'source' => 'filament_trash_bin',
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $record->forceDelete();
            });

            if (! empty($mediaPathsToDelete)) {
                $photoStorage = app(PhotoStorageService::class);
                foreach ($mediaPathsToDelete as $path) {
                    $photoStorage->deleteFile($path);
                }
            }

            Notification::make()->title('Data telah dihapus permanen')->warning()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Hapus Permanen')
                ->body('Terjadi kesalahan saat menghapus data permanen: '.$e->getMessage())
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
