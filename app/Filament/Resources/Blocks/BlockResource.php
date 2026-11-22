<?php

namespace App\Filament\Resources\Blocks;

use App\Filament\Resources\Blocks\Pages\ManageBlocks;
use App\Models\Block;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Wilayah & Kepemilikan';

    protected static ?string $navigationLabel = 'Blok';

    protected static ?string $modelLabel = 'Blok';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.view');
    }

    public static function canCreate(): bool
    {
        return (bool) (auth()->user()?->hasPermission('territory.manage') || auth()->user()?->hasPermission('blocks.request'));
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission('territory.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) (auth()->user()?->hasPermission('territory.manage') || auth()->user()?->hasPermission('blocks.request'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('village_id')->label('Desa')->relationship('village', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Nama Blok')->required()->maxLength(100),
            TextInput::make('block_code')->label('Kode Blok (2 Digit)')->maxLength(10)->helperText('Otomatis dibuat jika kosong'),
            TextInput::make('area_hectares')->label('Luas (Ha)')->numeric()->minValue(0),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('block_code')->label('Kode')->searchable()->sortable()->badge()->placeholder('-'),
                TextColumn::make('name')->label('Blok')->searchable()->sortable(),
                TextColumn::make('village.name')->label('Desa')->searchable()->sortable(),
                TextColumn::make('area_hectares')->label('Luas (Ha)')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('gardens_count')->label('Kebun')->counts('gardens')->sortable(),
                TextColumn::make('trees_count')->label('Pohon')->counts('trees')->sortable(),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ucfirst(str_replace('_', ' ', $state ?? 'approved')))
                    ->color(fn (?string $state) => match ($state) {
                        'approved' => 'success',
                        'pending_addition' => 'warning',
                        'pending_deletion' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('village_id')->label('Desa')->relationship('village', 'name'),
                SelectFilter::make('approval_status')->label('Status Persetujuan')->options([
                    'approved' => 'Disetujui',
                    'pending_addition' => 'Menunggu Penambahan',
                    'pending_deletion' => 'Menunggu Penghapusan',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Selengkapnya')
                    ->modalHeading(fn (Block $record) => "Detail Blok: {$record->name}")
                    ->modalContent(fn (Block $record) => view('filament.modals.block-detail', ['record' => $record]))
                    ->modalSubmitAction(false),

                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Block $record) => $record->approval_status !== 'approved' && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('super_admin')))
                    ->action(function (Block $record) {
                        if ($record->approval_status === 'pending_deletion') {
                            $record->delete();
                            Notification::make()->title('Blok berhasil dihapus sesuai permohonan')->success()->send();
                        } else {
                            $record->update(['approval_status' => 'approved']);
                            Notification::make()->title('Penambahan blok disetujui')->success()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Block $record) => $record->approval_status !== 'approved' && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('super_admin')))
                    ->action(function (Block $record) {
                        if ($record->approval_status === 'pending_addition') {
                            $record->forceDelete();
                            Notification::make()->title('Permohonan penambahan blok ditolak')->warning()->send();
                        } else {
                            $record->update(['approval_status' => 'approved']);
                            Notification::make()->title('Permohonan penghapusan blok ditolak')->info()->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()->visible(fn () => auth()->user()?->hasRole('super_admin')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlocks::route('/'),
        ];
    }
}
