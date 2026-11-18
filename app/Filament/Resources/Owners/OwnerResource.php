<?php

namespace App\Filament\Resources\Owners;

use App\Filament\Resources\Owners\Pages\ManageOwners;
use App\Models\Owner;
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

class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Pemilik Kebun';

    protected static ?string $modelLabel = 'Pemilik Kebun';

    protected static ?int $navigationSort = 4;

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
            Select::make('owner_type')
                ->label('Tipe Pemilik')
                ->options(['individual' => 'Perorangan', 'organization' => 'Organisasi'])
                ->default('individual')
                ->required(),
            TextInput::make('name')->label('Nama Lengkap')->required()->maxLength(150),
            TextInput::make('phone')->label('Nomor Telepon')->tel()->maxLength(30),
            Textarea::make('address')->label('Alamat')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('owner_type')->label('Tipe')->badge(),
                TextColumn::make('phone')->label('Telepon')->searchable()->placeholder('-'),
                TextColumn::make('garden_owners_count')->label('Kebun')->counts('gardenOwners')->sortable(),
            ])
            ->filters([
                SelectFilter::make('owner_type')->label('Tipe')->options(['individual' => 'Perorangan', 'organization' => 'Organisasi']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOwners::route('/'),
        ];
    }
}
