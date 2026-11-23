<?php

namespace App\Filament\Resources\Trees;

use App\Filament\Resources\Trees\Pages\ManageTrees;
use App\Models\Tree;
use App\Services\TreeQrCodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TreeResource extends Resource
{
    protected static ?string $model = Tree::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Pohon & Varietas';

    protected static ?string $navigationLabel = 'Pohon Durian';

    protected static ?string $modelLabel = 'Pohon Durian';

    protected static ?int $navigationSort = 2;

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
            Select::make('garden_id')->label('Kebun')->relationship('garden', 'name')->searchable()->preload()->required(),
            Select::make('variety_id')->label('Varietas')->relationship('variety', 'name')->searchable()->preload(),
            TextInput::make('tree_code')->label('Kode Pohon')->required()->maxLength(100),
            TextInput::make('tree_identity_number')
                ->label('ID / NIK Pohon')
                ->disabled()
                ->dehydrated(false)
                ->helperText('Otomatis di-generate sistem: [Desa]-[Blok]-[Kebun]-[Varietas]-[NomorUrut]'),
            TextInput::make('tree_name')->label('Nama / Label Pohon')->maxLength(150),
            DatePicker::make('planted_at')->label('Tanggal Tanam'),
            Select::make('status')->label('Status')->options(Tree::STATUS_LABELS)->default('active')->required(),
            FileUpload::make('photo_path')
                ->label('Foto Pohon (Opsional)')
                ->image()
                ->disk('public')
                ->directory('trees')
                ->maxSize(5120)
                ->columnSpanFull()
                ->helperText('Format JPG, PNG, atau WebP maksimal 5MB. Tidak wajib.'),
            TextInput::make('latitude')->label('Latitude')->numeric(),
            TextInput::make('longitude')->label('Longitude')->numeric(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('Foto')->disk('public')->circular()->placeholder('-'),
                TextColumn::make('tree_identity_number')->label('NIK Pohon')->searchable()->sortable()->badge()->color('primary'),
                TextColumn::make('tree_code')->label('Kode')->searchable()->sortable()->badge(),
                TextColumn::make('tree_name')->label('Nama')->searchable()->placeholder('-'),
                TextColumn::make('variety.name')->label('Varietas')->sortable()->placeholder('-'),
                TextColumn::make('garden.name')->label('Kebun')->sortable(),
                TextColumn::make('garden.block.name')->label('Blok'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Tree::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'monitoring' => 'info',
                        'sick', 'unproductive' => 'warning',
                        'dead', 'removed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('planted_at')->label('Tgl Tanam')->date('d M Y')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('garden_id')->label('Kebun')->relationship('garden', 'name'),
                SelectFilter::make('variety_id')->label('Varietas')->relationship('variety', 'name'),
                SelectFilter::make('status')->label('Status')->options(Tree::STATUS_LABELS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('qr_code')
                    ->label('QR Code')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('info')
                    ->modalHeading(fn (Tree $record) => "QR Code Pohon - {$record->tree_code}")
                    ->modalContent(function (Tree $record) {
                        $qrService = app(TreeQrCodeService::class);
                        $qrCode = $qrService->ensureForTree($record);
                        $svg = $qrService->render($qrCode, 'svg', 220)->getString();

                        return view('filament.components.qr-modal', [
                            'svg' => $svg,
                            'url' => $qrCode->qr_code,
                            'token' => $qrCode->public_token,
                            'tree' => $record->loadMissing(['garden', 'variety']),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin')),
                ForceDeleteAction::make()
                    ->visible(fn (Model $record) => $record->trashed() && (bool) auth()->user()?->hasRole('super_admin')),
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
            'index' => ManageTrees::route('/'),
        ];
    }
}
