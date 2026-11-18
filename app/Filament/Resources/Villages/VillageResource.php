<?php

namespace App\Filament\Resources\Villages;

use App\Filament\Resources\Villages\Pages\ManageVillages;
use App\Models\Village;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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
            TextInput::make('name')->label('Nama Desa')->required()->maxLength(150),
            TextInput::make('district')->label('Kecamatan')->maxLength(150),
            TextInput::make('regency')->label('Kabupaten / Kota')->maxLength(150),
            TextInput::make('province')->label('Provinsi')->maxLength(150),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Desa')->searchable()->sortable(),
                TextColumn::make('district')->label('Kecamatan')->searchable()->placeholder('-'),
                TextColumn::make('regency')->label('Kabupaten')->searchable()->placeholder('-'),
                TextColumn::make('province')->label('Provinsi')->searchable()->placeholder('-'),
                TextColumn::make('blocks_count')->label('Blok')->counts('blocks')->sortable(),
                TextColumn::make('gardens_count')->label('Kebun')->counts('gardens')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVillages::route('/'),
        ];
    }
}
