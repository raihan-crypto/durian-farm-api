<?php

namespace App\Filament\Resources\Villages;

use App\Filament\Resources\Villages\Pages\ManageVillages;
use App\Models\Village;
use App\Support\BanyumasRegionData;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                ->afterStateUpdated(fn (Set $set) => $set('name', null)),

            Select::make('name')
                ->label('Nama Desa')
                ->options(fn (Get $get) => BanyumasRegionData::getVillageOptions($get('district')))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                    if ($state && $get('district')) {
                        $code = BanyumasRegionData::findVillageCode($state, $get('district'));
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
                RestoreAction::make(),
                ForceDeleteAction::make()->visible(fn () => auth()->user()?->hasRole('super_admin')),
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
