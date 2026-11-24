<?php

namespace App\Filament\Resources\HarvestRecords;

use App\Filament\Resources\HarvestRecords\Pages\ManageHarvestRecords;
use App\Models\HarvestRecord;
use App\Services\TerritoryScopeService;
use BackedEnum;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HarvestRecordResource extends Resource
{
    protected static ?string $model = HarvestRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Panen';

    protected static ?string $navigationLabel = 'Hasil Panen';

    protected static ?string $modelLabel = 'Hasil Panen';

    protected static ?string $pluralModelLabel = 'Hasil Panen';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.record');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('harvest.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('session_id')->label('Sesi Panen')->relationship('session', 'session_name')->searchable()->preload()->required(),
            Select::make('garden_id')->label('Kebun')->relationship('garden', 'name')->searchable()->preload()->required(),
            Select::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code')->searchable()->preload()->required(),
            TextInput::make('fruit_count')->label('Jumlah Buah')->numeric()->minValue(0)->required(),
            TextInput::make('total_weight_kg')->label('Total Berat (kg)')->numeric()->minValue(0)->required(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.harvest_date')->label('Tanggal Panen')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('garden.name')->label('Kebun')->sortable(),
                TextColumn::make('tree.tree_code')->label('Kode Pohon')->searchable()->badge(),
                TextColumn::make('tree.variety.name')->label('Varietas')->placeholder('-'),
                TextColumn::make('fruit_count')->label('Jumlah Buah')->numeric()->sortable(),
                TextColumn::make('total_weight_kg')->label('Total Berat (kg)')->numeric(decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                SelectFilter::make('garden_id')->label('Kebun')->relationship('garden', 'name'),
                SelectFilter::make('session_id')->label('Sesi')->relationship('session', 'session_name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
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
            'index' => ManageHarvestRecords::route('/'),
        ];
    }
}
