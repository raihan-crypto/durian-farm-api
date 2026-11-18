<?php

namespace App\Filament\Resources\DurianVarieties;

use App\Filament\Resources\DurianVarieties\Pages\ManageDurianVarieties;
use App\Models\DurianVariety;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DurianVarietyResource extends Resource
{
    protected static ?string $model = DurianVariety::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Pohon & Varietas';

    protected static ?string $navigationLabel = 'Varietas Durian';

    protected static ?string $modelLabel = 'Varietas Durian';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('trees.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama Varietas')->required()->maxLength(150)->unique(ignoreRecord: true),
            TextInput::make('origin')->label('Asal')->maxLength(150),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Textarea::make('characteristics')->label('Karakteristik')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->badge()->placeholder('-'),
                TextColumn::make('name')->label('Varietas')->searchable()->sortable(),
                TextColumn::make('origin')->label('Asal')->placeholder('-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('trees_count')->label('Jumlah Pohon')->counts('trees')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDurianVarieties::route('/'),
        ];
    }
}
