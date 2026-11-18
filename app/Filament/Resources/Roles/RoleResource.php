<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use BackedEnum;
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

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem & Audit';

    protected static ?string $navigationLabel = 'Role & Permission';

    protected static ?string $modelLabel = 'Role';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('roles.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('roles.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('roles.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama Role')->required()->maxLength(100)->unique(ignoreRecord: true),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Select::make('permissions')->label('Permissions')->relationship('permissions', 'name')->multiple()->preload()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Role')->badge()->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(60),
                TextColumn::make('permissions_count')->label('Permissions')->counts('permissions'),
                TextColumn::make('users_count')->label('Pengguna')->counts('users'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}
