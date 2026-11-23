<?php

namespace App\Filament\Resources\Villages;

use App\Filament\Resources\Villages\Pages\ManageVillages;
use App\Models\Village;
use App\Support\BanyumasRegionData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VillageResource extends Resource
{
    protected static ?string $model = Village::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Desa';

    protected static ?string $modelLabel = 'Desa';

    protected static ?int $navigationSort = 1;

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
            Select::make('province')
                ->label('Provinsi')
                ->options(['Jawa Tengah' => 'Jawa Tengah'])
                ->default('Jawa Tengah')
                ->required(),

            Select::make('regency')
                ->label('Kabupaten / Kota')
                ->options(['Kabupaten Banyumas' => 'Kabupaten Banyumas'])
                ->default('Kabupaten Banyumas')
                ->required(),

            Select::make('district')
                ->label('Kecamatan')
                ->options(BanyumasRegionData::getDistrictOptions())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('name', null)),

            Select::make('name')
                ->label('Nama Desa')
                ->options(function ($get, ?Village $record) {
                    $district = $get('district') ?? $record?->district;

                    return $district ? BanyumasRegionData::getVillageOptions($district) : [];
                })
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, $get, $set) {
                    $district = $get('district');
                    if ($state && $district) {
                        $code = BanyumasRegionData::findVillageCode($state, $district);
                        if ($code) {
                            $set('village_code', $code);
                        }
                    }
                }),

            TextInput::make('village_code')
                ->label('Kode Desa (2 Digit)')
                ->readOnly()
                ->maxLength(10)
                ->helperText('Kode 2 digit otomatis berdasarkan urutan desa di kecamatan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('village_code')->label('Kode')->searchable()->sortable()->badge()->placeholder('-'),
                TextColumn::make('name')->label('Desa')->searchable()->sortable(),
                TextColumn::make('district')->label('Kecamatan')->searchable()->placeholder('-'),
                TextColumn::make('regency')->label('Kabupaten')->searchable()->placeholder('-'),
                TextColumn::make('blocks_count')->label('Blok')->counts('blocks')->sortable(),
                TextColumn::make('gardens_count')->label('Kebun')->counts('gardens')->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Selengkapnya')
                    ->modalHeading(fn (Village $record) => "Detail Desa: {$record->name}")
                    ->modalContent(fn (Village $record) => view('filament.modals.village-detail', ['record' => $record]))
                    ->modalSubmitAction(false),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin')),
                ForceDeleteAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin'))
                    ->before(function (Action $action, Model $record) {
                        if ($record->blocks()->withTrashed()->exists()) {
                            Notification::make()
                                ->title('Gagal Hapus Permanen')
                                ->body('Desa ini tidak dapat dihapus permanen karena masih ada data blok (aktif atau di tempat sampah) yang terhubung.')
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
            'index' => ManageVillages::route('/'),
        ];
    }
}
