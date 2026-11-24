<?php

namespace App\Filament\Resources\HarvestSessions;

use App\Filament\Resources\HarvestSessions\Pages\ManageHarvestSessions;
use App\Models\HarvestSession;
use App\Services\TerritoryScopeService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class HarvestSessionResource extends Resource
{
    protected static ?string $model = HarvestSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Panen';

    protected static ?string $navigationLabel = 'Sesi Panen';

    protected static ?string $modelLabel = 'Sesi Panen';

    protected static ?string $pluralModelLabel = 'Sesi Panen';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('season_id')->label('Musim Panen')->relationship('season', 'name')->searchable()->preload()->required(),
            Select::make('garden_id')->label('Kebun')->relationship('garden', 'name')->searchable()->preload()->required(),
            DateTimePicker::make('harvest_date')->label('Waktu Panen')->default(now())->required(),
            TextInput::make('session_name')->label('Nama Sesi')->maxLength(150),
            TextInput::make('weather_condition')->label('Kondisi Cuaca')->maxLength(100),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('harvest_date')->label('Tanggal Panen')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('session_name')->label('Sesi')->searchable()->placeholder('-'),
                TextColumn::make('season.name')->label('Musim')->sortable(),
                TextColumn::make('garden.name')->label('Kebun')->sortable(),
                TextColumn::make('records_count')->label('Pohon Dipanen')->counts('records'),
            ])
            ->filters([
                SelectFilter::make('season_id')->label('Musim')->relationship('season', 'name'),
                SelectFilter::make('garden_id')->label('Kebun')->relationship('garden', 'name'),
            ])
            ->defaultSort('harvest_date', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user) {
            $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
            if ($accessibleGardenIds !== null) {
                $query->whereIn('garden_id', $accessibleGardenIds);
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHarvestSessions::route('/'),
        ];
    }
}
