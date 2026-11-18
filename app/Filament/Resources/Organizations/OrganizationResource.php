<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\ManageOrganizations;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem & Audit';

    protected static ?string $navigationLabel = 'Organisasi';

    protected static ?string $modelLabel = 'Organisasi';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('organizations.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('organizations.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('organizations.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('organizations.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama Organisasi')->required()->maxLength(150),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Organisasi')->searchable()->sortable(),
                TextColumn::make('users_count')->label('Pengguna')->counts('users'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrganizations::route('/'),
        ];
    }
}
