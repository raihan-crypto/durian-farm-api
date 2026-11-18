<?php

namespace App\Filament\Resources\TreeMeasurements;

use App\Filament\Resources\TreeMeasurements\Pages\ManageTreeMeasurements;
use App\Models\TreeMeasurement;
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
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TreeMeasurementResource extends Resource
{
    protected static ?string $model = TreeMeasurement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Pohon & Varietas';

    protected static ?string $navigationLabel = 'Pengukuran Pohon';

    protected static ?string $modelLabel = 'Pengukuran Pohon';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.measure');
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
            DateTimePicker::make('measured_at')->label('Waktu Pengukuran')->default(now())->required(),
            TextInput::make('diameter_cm')->label('Diameter Batang (cm)')->numeric()->minValue(0),
            TextInput::make('height_m')->label('Tinggi Pohon (m)')->numeric()->minValue(0),
            TextInput::make('canopy_width_m')->label('Lebar Tajuk (m)')->numeric()->minValue(0),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('measured_at')->label('Tanggal Ukur')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('tree.tree_code')->label('Kode Pohon')->searchable()->badge(),
                TextColumn::make('diameter_cm')->label('Diameter (cm)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('height_m')->label('Tinggi (m)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('canopy_width_m')->label('Tajuk (m)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('recorder.name')->label('Petugas')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('tree_id')->label('Pohon')->relationship('tree', 'tree_code'),
            ])
            ->defaultSort('measured_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTreeMeasurements::route('/'),
        ];
    }
}
