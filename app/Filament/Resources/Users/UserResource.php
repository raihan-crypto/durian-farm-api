<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem & Audit';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('users.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('users.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('users.manage');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $record */
        if ($record->hasRole('super_admin')) {
            return false;
        }

        return (bool) (auth()->user()?->hasRole('super_admin') || auth()->user()?->hasPermission('users.manage'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_id')->label('Organisasi')->relationship('organization', 'name')->searchable()->preload(),
            TextInput::make('name')->label('Nama Lengkap')->required()->maxLength(150),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(150),
            TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
            Select::make('roles')->label('Role')->relationship('roles', 'name')->multiple()->preload(),
            Select::make('village_id')
                ->label('Desa Terikat')
                ->relationship('village', 'name')
                ->searchable()
                ->preload()
                ->helperText('Wajib diisi apabila pengguna memiliki role Pemerintah Desa'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')->label('Role')->badge(),
                TextColumn::make('village.name')->label('Desa Terikat')->placeholder('-'),
                TextColumn::make('organization.name')->label('Organisasi')->placeholder('-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status Aktif'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (User $record) => ! $record->trashed() && ! $record->hasRole('super_admin')),
                RestoreAction::make()->visible(fn (User $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin')),
                ForceDeleteAction::make()->visible(fn (User $record) => $record->trashed() && ! $record->hasRole('super_admin') && (bool) auth()->user()?->hasRole('super_admin')),
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
            'index' => ManageUsers::route('/'),
        ];
    }
}
