<?php

namespace App\Filament\Resources\Farmers;

use App\Filament\Resources\Farmers\Pages\ManageFarmers;
use App\Models\Farmer;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FarmerResource extends Resource
{
    protected static ?string $model = Farmer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Petani';

    protected static ?string $modelLabel = 'Petani';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('ownership.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('ownership.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('ownership.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('ownership.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Akun Pengguna')->relationship('user', 'email')->searchable()->preload()->nullable(),
            TextInput::make('name')->label('Nama Petani')->required()->maxLength(150),
            TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            Textarea::make('address')->label('Alamat')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Akun Email')->placeholder('Belum terhubung'),
                TextColumn::make('phone')->label('Telepon')->placeholder('-'),
                TextColumn::make('block_farmers_count')->label('Blok')->counts('blockFarmers'),
                TextColumn::make('garden_farmers_count')->label('Kebun')->counts('gardenFarmers'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFarmers::route('/'),
        ];
    }
}
