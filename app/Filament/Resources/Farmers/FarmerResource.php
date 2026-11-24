<?php

namespace App\Filament\Resources\Farmers;

use App\Filament\Resources\Farmers\Pages\ManageFarmers;
use App\Models\Farmer;
use App\Services\TerritoryScopeService;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FarmerResource extends Resource
{
    protected static ?string $model = Farmer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Tenaga Kerja';

    protected static ?string $modelLabel = 'Tenaga Kerja';

    protected static ?string $pluralModelLabel = 'Tenaga Kerja';

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
            Select::make('user_id')
                ->label('Akun Pengguna Terkait')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Tautkan dengan akun mobile / login pekerja jika ada'),
            TextInput::make('name')->label('Nama Tenaga Kerja')->required()->maxLength(150),
            TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            Textarea::make('address')->label('Alamat')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Akun Email')->placeholder('Belum terhubung'),
                TextColumn::make('phone')->label('Telepon')->placeholder('-'),
                TextColumn::make('gardens_count')->label('Kebun Tugas')->counts('gardens')->sortable(),
                TextColumn::make('blocks_count')->label('Blok Tugas')->counts('blocks')->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Selengkapnya')
                    ->modalHeading(fn (Farmer $record) => "Detail Tenaga Kerja: {$record->name}")
                    ->modalContent(fn (Farmer $record) => view('filament.modals.farmer-detail', ['record' => $record]))
                    ->modalSubmitAction(false),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin') && ! $user->hasRole('admin')) {
            $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
            if ($accessibleGardenIds !== null) {
                $query->whereHas('gardens', function ($q) use ($accessibleGardenIds) {
                    $q->whereIn('gardens.id', $accessibleGardenIds);
                });
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFarmers::route('/'),
        ];
    }
}
