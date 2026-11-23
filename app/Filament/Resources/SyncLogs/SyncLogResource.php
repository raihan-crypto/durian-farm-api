<?php

namespace App\Filament\Resources\SyncLogs;

use App\Filament\Resources\SyncLogs\Pages\ManageSyncLogs;
use App\Models\SyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem & Audit';

    protected static ?string $navigationLabel = 'Log Sinkronisasi';

    protected static ?string $modelLabel = 'Log Sinkronisasi';

    protected static ?string $pluralModelLabel = 'Log Sinkronisasi';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('audit.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('-'),
                TextColumn::make('device_identifier')->label('Perangkat')->placeholder('-'),
                TextColumn::make('entity_type')->label('Entitas')->badge(),
                TextColumn::make('sync_status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    'success' => 'success',
                    'conflict' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('error_message')->label('Keterangan')->limit(40)->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('sync_status')->label('Status')->options([
                    'pending' => 'Pending',
                    'success' => 'Success',
                    'conflict' => 'Conflict',
                    'failed' => 'Failed',
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSyncLogs::route('/'),
        ];
    }
}
