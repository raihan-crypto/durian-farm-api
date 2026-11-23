<?php

namespace App\Filament\Resources\Owners;

use App\Filament\Resources\Owners\Pages\ManageOwners;
use App\Models\Owner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Petani';

    protected static ?string $modelLabel = 'Petani';

    protected static ?string $pluralModelLabel = 'Petani';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('ownership.view');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('super_admin') || $user->hasRole('admin')) && $user->hasPermission('ownership.manage'));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('super_admin') || $user->hasRole('admin')) && $user->hasPermission('ownership.manage'));
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('super_admin') || $user->hasRole('admin')) && $user->hasPermission('ownership.manage'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin') && ! $user->hasRole('admin') && $user->hasRole('petani')) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Akun Pengguna Terkait')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Tautkan dengan akun login pengguna jika ada'),
            Select::make('owner_type')
                ->label('Tipe Petani')
                ->options([
                    'individual' => 'Perorangan',
                    'organization' => 'Organisasi / Kelompok Tani',
                ])
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
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('owner_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'organization' ? 'Kelompok Tani' : 'Perorangan')
                    ->color(fn (string $state) => $state === 'organization' ? 'info' : 'gray'),
                TextColumn::make('phone')->label('Telepon')->searchable()->placeholder('-'),
                TextColumn::make('gardens_count')->label('Kebun Terikat')->counts('gardens')->sortable(),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label('Tipe')
                    ->options([
                        'individual' => 'Perorangan',
                        'organization' => 'Organisasi / Kelompok Tani',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Selengkapnya')
                    ->modalHeading(fn (Owner $record) => "Detail Petani: {$record->name}")
                    ->modalContent(fn (Owner $record) => view('filament.modals.owner-detail', ['record' => $record]))
                    ->modalSubmitAction(false),
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
