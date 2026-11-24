<?php

namespace App\Filament\Resources\FruitDevelopmentRecords;

use App\Filament\Resources\FruitDevelopmentRecords\Pages\ManageFruitDevelopmentRecords;
use App\Models\FruitDevelopmentRecord;
use App\Services\TerritoryScopeService;
use BackedEnum;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FruitDevelopmentRecordResource extends Resource
{
    protected static ?string $model = FruitDevelopmentRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Produk & Aktivitas';

    protected static ?string $navigationLabel = 'Perkembangan Buah';

    protected static ?string $modelLabel = 'Perkembangan Buah';

    protected static ?string $pluralModelLabel = 'Perkembangan Buah';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('activities.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('activities.record');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code')->searchable()->preload()->required(),
            DatePicker::make('observation_date')->label('Tanggal Pengamatan')->default(now())->required(),
            Select::make('development_stage')
                ->label('Tahap Perkembangan')
                ->options((array) config('durian.activities.fruit_development_stages', []))
                ->required(),
            TextInput::make('fruit_count')->label('Jumlah Buah')->numeric()->minValue(0),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('observation_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('tree.tree_code')->label('Pohon')->searchable()->badge(),
                TextColumn::make('development_stage')->label('Tahap')->badge(),
                TextColumn::make('fruit_count')->label('Jml Buah')->numeric()->placeholder('-'),
                TextColumn::make('recorder.name')->label('Petugas')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('development_stage')->label('Tahap')->options((array) config('durian.activities.fruit_development_stages', [])),
                SelectFilter::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code'),
            ])
            ->defaultSort('observation_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user) {
            $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
            if ($accessibleGardenIds !== null) {
                $query->whereHas('tree', function ($q) use ($accessibleGardenIds) {
                    $q->whereIn('garden_id', $accessibleGardenIds);
                });
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFruitDevelopmentRecords::route('/'),
        ];
    }
}
