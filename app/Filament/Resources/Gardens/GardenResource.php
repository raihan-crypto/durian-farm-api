<?php

namespace App\Filament\Resources\Gardens;

use App\Filament\Resources\Gardens\Pages\ManageGardens;
use App\Models\Garden;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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
            Select::make('block_id')->label('Blok')->relationship('block', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Nama Kebun')->required()->maxLength(150),
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
            ->columns([
                TextColumn::make('name')->label('Kebun')->searchable()->sortable(),
                TextColumn::make('block.name')->label('Blok')->sortable(),
                TextColumn::make('block.village.name')->label('Desa')->sortable(),
                TextColumn::make('area_hectares')->label('Luas (Ha)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('trees_count')->label('Pohon')->counts('trees')->sortable(),
            ])
            ->filters([
                SelectFilter::make('block_id')->label('Blok')->relationship('block', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGardens::route('/'),
        ];
    }
}
