<?php

namespace App\Filament\Widgets;

use App\Models\HarvestRecord;
use App\Services\TerritoryScopeService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestHarvestRecordsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Hasil Panen Terbaru';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = HarvestRecord::query()->with(['tree.variety', 'garden', 'session']);
                $user = auth()->user();
                if ($user) {
                    $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
                    if ($accessibleGardenIds !== null) {
                        $query->whereIn('garden_id', $accessibleGardenIds);
                    }
                }

                return $query->latest()->limit(10);
            })
            ->columns([
                TextColumn::make('session.harvest_date')
                    ->label('Tanggal Panen')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('garden.name')
                    ->label('Kebun'),
                TextColumn::make('tree.tree_code')
                    ->label('Kode Pohon')
                    ->badge(),
                TextColumn::make('tree.variety.name')
                    ->label('Varietas')
                    ->placeholder('-'),
                TextColumn::make('fruit_count')
                    ->label('Jumlah Buah')
                    ->numeric(),
                TextColumn::make('total_weight_kg')
                    ->label('Berat (kg)')
                    ->numeric(decimalPlaces: 2),
            ])
            ->paginated(false);
    }
}
