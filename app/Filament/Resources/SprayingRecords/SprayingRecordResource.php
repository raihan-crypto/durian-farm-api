<?php

namespace App\Filament\Resources\SprayingRecords;

use App\Filament\Resources\SprayingRecords\Pages\ManageSprayingRecords;
use App\Models\SprayingRecord;
use App\Services\TerritoryScopeService;
use BackedEnum;
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

class SprayingRecordResource extends Resource
{
    protected static ?string $model = SprayingRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static string|UnitEnum|null $navigationGroup = 'Produk & Aktivitas';

    protected static ?string $navigationLabel = 'Penyemprotan';

    protected static ?string $modelLabel = 'Penyemprotan';

    protected static ?string $pluralModelLabel = 'Penyemprotan';

    protected static ?int $navigationSort = 4;

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
            DateTimePicker::make('activity_date')->label('Tanggal Penyemprotan')->default(now())->required(),
            TextInput::make('spraying_method')->label('Metode Penyemprotan')->maxLength(100),
            Textarea::make('dosage_notes')->label('Catatan Dosis')->columnSpanFull(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('activity_date')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('tree.tree_code')->label('Pohon')->searchable()->badge(),
                TextColumn::make('spraying_method')->label('Metode')->placeholder('-'),
                TextColumn::make('items_count')->label('Jml Produk')->counts('items'),
                TextColumn::make('recorder.name')->label('Petugas')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code'),
            ])
            ->defaultSort('activity_date', 'desc');
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
            'index' => ManageSprayingRecords::route('/'),
        ];
    }
}
