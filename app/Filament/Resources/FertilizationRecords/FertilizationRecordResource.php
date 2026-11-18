<?php

namespace App\Filament\Resources\FertilizationRecords;

use App\Filament\Resources\FertilizationRecords\Pages\ManageFertilizationRecords;
use App\Models\FertilizationRecord;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FertilizationRecordResource extends Resource
{
    protected static ?string $model = FertilizationRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Produk & Aktivitas';

    protected static ?string $navigationLabel = 'Pemupukan';

    protected static ?string $modelLabel = 'Pemupukan';

    protected static ?int $navigationSort = 3;

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
            DateTimePicker::make('activity_date')->label('Tanggal Pemupukan')->default(now())->required(),
            Select::make('method')
                ->label('Metode')
                ->options((array) config('durian.activities.fertilization_methods', []))
                ->default('tabur')
                ->required(),
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
                TextColumn::make('method')->label('Metode')->badge(),
                TextColumn::make('items_count')->label('Jml Produk')->counts('items'),
                TextColumn::make('recorder.name')->label('Petugas')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('method')->label('Metode')->options((array) config('durian.activities.fertilization_methods', [])),
                SelectFilter::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code'),
            ])
            ->defaultSort('activity_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFertilizationRecords::route('/'),
        ];
    }
}
