<?php

namespace App\Filament\Resources\Gardens;

use App\Filament\Resources\Gardens\Pages\ManageGardens;
use App\Models\Block;
use App\Models\Garden;
use App\Models\Village;
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

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.manage');
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

            TextInput::make('name')->label('Nama Kebun')->required()->maxLength(150),
            TextInput::make('garden_code')->label('Kode Kebun (2 Digit)')->maxLength(20)->helperText('Otomatis dibuat jika kosong'),

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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGardens::route('/'),
        ];
    }
}
