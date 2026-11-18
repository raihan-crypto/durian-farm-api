<?php

namespace App\Filament\Resources\AgriculturalProducts;

use App\Filament\Resources\AgriculturalProducts\Pages\ManageAgriculturalProducts;
use App\Models\AgriculturalProduct;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AgriculturalProductResource extends Resource
{
    protected static ?string $model = AgriculturalProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Produk & Aktivitas';

    protected static ?string $navigationLabel = 'Produk Pertanian';

    protected static ?string $modelLabel = 'Produk Pertanian';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('products.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('products.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('products.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('products.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Nama Produk')->required()->maxLength(150),
            TextInput::make('brand')->label('Merek')->maxLength(120),
            TextInput::make('active_ingredient')->label('Bahan Aktif')->maxLength(150),
            TextInput::make('unit')->label('Satuan Default')->maxLength(50),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Produk')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Kategori')->badge()->sortable(),
                TextColumn::make('brand')->label('Merek')->placeholder('-'),
                TextColumn::make('unit')->label('Satuan')->placeholder('-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAgriculturalProducts::route('/'),
        ];
    }
}
