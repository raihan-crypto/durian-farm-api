<?php

namespace App\Filament\Resources\Blocks;

use App\Filament\Resources\Blocks\Pages\ManageBlocks;
use App\Models\Block;
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

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Blok';

    protected static ?string $modelLabel = 'Blok';

    protected static ?int $navigationSort = 2;

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
            Select::make('village_id')->label('Desa')->relationship('village', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Nama Blok')->required()->maxLength(100),
            TextInput::make('area_hectares')->label('Luas (Ha)')->numeric()->minValue(0),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Blok')->searchable()->sortable(),
                TextColumn::make('village.name')->label('Desa')->searchable()->sortable(),
                TextColumn::make('area_hectares')->label('Luas (Ha)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('gardens_count')->label('Kebun')->counts('gardens')->sortable(),
                TextColumn::make('trees_count')->label('Pohon')->counts('trees')->sortable(),
            ])
            ->filters([
                SelectFilter::make('village_id')->label('Desa')->relationship('village', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlocks::route('/'),
        ];
    }
}
