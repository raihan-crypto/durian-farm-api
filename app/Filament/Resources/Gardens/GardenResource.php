<?php

namespace App\Filament\Resources\Gardens;

use App\Filament\Resources\Gardens\Pages\ManageGardens;
use App\Models\Block;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\Village;
use App\Services\TerritoryDuplicateGuard;
use App\Services\TerritoryScopeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class GardenResource extends Resource
{
    protected static ?string $model = Garden::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Kebun';

    protected static ?string $modelLabel = 'Kebun';

    protected static ?string $pluralModelLabel = 'Kebun';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasPermission('territory.view') || $user?->hasPermission('gardens.manage'));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasPermission('gardens.manage') || $user?->hasPermission('territory.manage'));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('petani')) {
            $ownerId = $user->ownerProfile?->id;
            if ($ownerId && $record instanceof Garden) {
                return $record->owners()->where('owners.id', $ownerId)->exists();
            }

            return false;
        }

        return (bool) $user->hasPermission('territory.manage');
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('petani')) {
            $ownerId = $user->ownerProfile?->id;
            if ($ownerId && $record instanceof Garden) {
                return $record->owners()->where('owners.id', $ownerId)->exists();
            }

            return false;
        }

        return (bool) $user->hasPermission('territory.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('village_id')
                ->label('Desa')
                ->options(fn () => Village::pluck('name', 'id'))
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(fn ($set) => $set('block_id', null))
                ->afterStateHydrated(function ($set, ?Garden $record) {
                    if ($record?->block?->village_id) {
                        $set('village_id', $record->block->village_id);
                    }
                })
                ->helperText('Pilih desa terlebih dahulu untuk memfilter daftar blok'),

            Select::make('block_id')
                ->label('Blok')
                ->options(function ($get, ?Garden $record) {
                    $villageId = $get('village_id') ?? $record?->block?->village_id;
                    if ($villageId) {
                        return Block::where('village_id', $villageId)->pluck('name', 'id');
                    }

                    return Block::pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('name')
                ->label('Nama Kebun')
                ->required()
                ->maxLength(150)
                ->rules([
                    fn ($get, ?Garden $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        $blockId = $get('block_id') ?? $record?->block_id;
                        $guard = app(TerritoryDuplicateGuard::class);
                        $dup = $guard->findDuplicateGarden($value, $blockId, $record?->id);
                        if ($dup) {
                            $fail($guard->message('Kebun', 'blok', $dup));
                        }
                    },
                ]),

            Select::make('ownership_type')
                ->label('Tipe Kepemilikan Kebun')
                ->options([
                    'individual' => 'Perorangan (1 Petani)',
                    'group' => 'Kelompok / Kelompok Tani (Beberapa Petani)',
                ])
                ->default('individual')
                ->required()
                ->live(),

            Select::make('owners')
                ->label('Petani Pengelola Kebun')
                ->relationship('owners', 'name')
                ->multiple()
                ->maxItems(fn ($get) => $get('ownership_type') === 'individual' ? 1 : null)
                ->searchable()
                ->preload()
                ->required()
                ->helperText(fn ($get) => $get('ownership_type') === 'individual'
                    ? 'Pilih 1 orang petani pemilik/pengelola kebun perorangan ini'
                    : 'Pilih satu atau lebih petani yang tergabung dalam kelompok pengelola kebun ini'),

            Select::make('farmers')
                ->label('Tenaga Kerja Kebun')
                ->relationship('farmers', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('Daftar tenaga kerja operasional yang ditugaskan di kebun ini'),

            TextInput::make('garden_code')
                ->label('Kode Kebun (2 Digit)')
                ->readOnly()
                ->placeholder('Otomatis dibuat sistem (contoh: 01, 02)')
                ->helperText('Kode 2 digit otomatis berdasarkan urutan kebun di dalam blok'),

            Select::make('status')
                ->label('Status Kebun')
                ->options([
                    'active' => 'Aktif',
                    'inactive' => 'Tidak aktif',
                ])
                ->default('active')
                ->required(),

            TextInput::make('area_hectares')->label('Luas (Ha)')->numeric()->minValue(0),
            TextInput::make('latitude')->label('Latitude')->numeric(),
            TextInput::make('longitude')->label('Longitude')->numeric(),
            Textarea::make('address')->label('Alamat')->columnSpanFull(),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('garden_code')->label('Kode')->searchable()->sortable()->badge()->placeholder('-'),
                TextColumn::make('name')->label('Kebun')->searchable()->sortable(),
                TextColumn::make('block.name')->label('Blok')->sortable(),
                TextColumn::make('block.village.name')->label('Desa')->sortable(),
                TextColumn::make('owners.name')->label('Petani Pengelola')->badge()->color('info')->placeholder('Belum ditentukan'),
                TextColumn::make('farmers_count')->label('Tenaga Kerja')->counts('farmers')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'active' ? 'Aktif' : 'Tidak aktif')
                    ->color(fn (?string $state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('area_hectares')->label('Luas (Ha)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('trees_count')->label('Pohon')->counts('trees')->sortable(),
            ])
            ->filters([
                SelectFilter::make('block_id')->label('Blok')->relationship('block', 'name'),
                SelectFilter::make('status')->label('Status')->options([
                    'active' => 'Aktif',
                    'inactive' => 'Tidak aktif',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Selengkapnya')
                    ->modalHeading(fn (Garden $record) => "Detail Kebun: {$record->name}")
                    ->modalContent(fn (Garden $record) => view('filament.modals.garden-detail', ['record' => $record]))
                    ->modalSubmitAction(false),
                Action::make('manage_workers')
                    ->label('Kelola Tenaga Kerja')
                    ->icon(Heroicon::OutlinedUsers)
                    ->color('info')
                    ->visible(function (Garden $record) {
                        if ($record->trashed()) {
                            return false;
                        }

                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }
                        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
                            return true;
                        }
                        if ($user->hasRole('petani')) {
                            $ownerId = $user->ownerProfile?->id;

                            return $ownerId && $record->owners()->where('owners.id', $ownerId)->exists();
                        }

                        return false;
                    })
                    ->fillForm(fn (Garden $record): array => [
                        'farmer_ids' => $record->farmers()->pluck('farmers.id')->all(),
                    ])
                    ->form([
                        Select::make('farmer_ids')
                            ->label('Daftar Tenaga Kerja Kebun')
                            ->options(fn () => Farmer::query()->orderBy('name')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Tambahkan atau kurangi tenaga kerja operasional yang ditugaskan di kebun ini'),
                    ])
                    ->action(function (Garden $record, array $data): void {
                        $record->farmers()->sync($data['farmer_ids'] ?? []);
                    })
                    ->modalHeading(fn (Garden $record) => "Kelola Tenaga Kerja: {$record->name}")
                    ->modalSubmitActionLabel('Simpan Tenaga Kerja')
                    ->successNotificationTitle('Tenaga kerja kebun berhasil diperbarui'),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin')),
                ForceDeleteAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin'))
                    ->before(function (Action $action, Model $record) {
                        if ($record->trees()->withTrashed()->exists()) {
                            Notification::make()
                                ->title('Gagal Hapus Permanen')
                                ->body('Kebun ini tidak dapat dihapus permanen karena masih ada data pohon (aktif atau di tempat sampah) yang terhubung.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = auth()->user();
        if ($user) {
            if ($user->hasRole('petani')) {
                $accessibleBlockIds = app(TerritoryScopeService::class)->getAccessibleBlockIds($user);
                if ($accessibleBlockIds !== null) {
                    $query->whereIn('block_id', $accessibleBlockIds);
                }
            } else {
                $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
                if ($accessibleGardenIds !== null) {
                    $query->whereIn('id', $accessibleGardenIds);
                }
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGardens::route('/'),
        ];
    }
}
