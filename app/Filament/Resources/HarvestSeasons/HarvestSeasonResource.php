<?php

namespace App\Filament\Resources\HarvestSeasons;

use App\Filament\Resources\HarvestSeasons\Pages\ManageHarvestSeasons;
use App\Models\HarvestSeason;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class HarvestSeasonResource extends Resource
{
    protected static ?string $model = HarvestSeason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Panen';

    protected static ?string $navigationLabel = 'Musim Panen';

    protected static ?string $modelLabel = 'Musim Panen';

    protected static ?string $pluralModelLabel = 'Musim Panen';

    protected static ?int $navigationSort = 1;

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
            Select::make('garden_id')->label('Kebun')->relationship('garden', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Nama Musim Panen')->required()->maxLength(150),
            DatePicker::make('start_date')->label('Tanggal Mulai')->required(),
            DatePicker::make('end_date')->label('Tanggal Selesai'),
            Select::make('status')
                ->label('Status')
                ->options(['planned' => 'Direncanakan', 'active' => 'Aktif', 'completed' => 'Selesai'])
                ->default('active')
                ->required(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Musim')->searchable()->sortable(),
                TextColumn::make('garden.name')->label('Kebun')->sortable(),
                TextColumn::make('start_date')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->label('Selesai')->date('d M Y')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('sessions_count')->label('Sesi')->counts('sessions'),
            ])
            ->filters([
                SelectFilter::make('garden_id')->label('Kebun')->relationship('garden', 'name'),
                SelectFilter::make('status')->label('Status')->options(['planned' => 'Direncanakan', 'active' => 'Aktif', 'completed' => 'Selesai']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHarvestSeasons::route('/'),
        ];
    }
}
