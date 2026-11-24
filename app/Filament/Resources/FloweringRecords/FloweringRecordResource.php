<?php

namespace App\Filament\Resources\FloweringRecords;

use App\Filament\Resources\FloweringRecords\Pages\ManageFloweringRecords;
use App\Models\FloweringRecord;
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

class FloweringRecordResource extends Resource
{
    protected static ?string $model = FloweringRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Produk & Aktivitas';

    protected static ?string $navigationLabel = 'Pembungaan';

    protected static ?string $modelLabel = 'Pembungaan';

    protected static ?string $pluralModelLabel = 'Pembungaan';

    protected static ?int $navigationSort = 5;

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
            DatePicker::make('flowering_date')->label('Tanggal Pengamatan')->default(now())->required(),
            Select::make('flowering_status')
                ->label('Status Pembungaan')
                ->options((array) config('durian.activities.flowering_statuses', []))
                ->required(),
            TextInput::make('flower_count')->label('Jumlah Bunga')->numeric()->minValue(0),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('flowering_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('tree.tree_code')->label('Pohon')->searchable()->badge(),
                TextColumn::make('flowering_status')->label('Status')->badge(),
                TextColumn::make('flower_count')->label('Jml Bunga')->numeric()->placeholder('-'),
                TextColumn::make('recorder.name')->label('Petugas')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('flowering_status')->label('Status')->options((array) config('durian.activities.flowering_statuses', [])),
                SelectFilter::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code'),
            ])
            ->defaultSort('flowering_date', 'desc');
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
            'index' => ManageFloweringRecords::route('/'),
        ];
    }
}
