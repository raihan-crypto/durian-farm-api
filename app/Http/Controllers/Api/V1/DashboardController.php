<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\HarvestRecord;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\Village;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Aggregated plantation dashboard summary scoped to user's territory access (SRS 9.1 & 11.3).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleVillageIds = $this->territoryScope->getAccessibleVillageIds($user);
        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($user);
        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($user);

        $villagesCount = Village::query()
            ->when($accessibleVillageIds !== null, fn ($q) => $q->whereIn('id', $accessibleVillageIds))
            ->count();

        $blocksCount = Block::query()
            ->when($accessibleBlockIds !== null, fn ($q) => $q->whereIn('id', $accessibleBlockIds))
            ->count();

        $gardensCount = Garden::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('id', $accessibleGardenIds))
            ->count();

        $treesBase = Tree::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds));

        $treesCount = (clone $treesBase)->count();

        $treesByStatus = (clone $treesBase)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count);

        $treesByVariety = DB::table('trees')
            ->leftJoin('durian_varieties', 'trees.variety_id', '=', 'durian_varieties.id')
            ->when($accessibleGardenIds !== null, function ($q) use ($accessibleGardenIds) {
                if (empty($accessibleGardenIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('trees.garden_id', $accessibleGardenIds);
                }
            })
            ->selectRaw('COALESCE(durian_varieties.name, "Belum Ditentukan") as variety_name, COUNT(trees.id) as total')
            ->groupBy('durian_varieties.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'variety' => $row->variety_name,
                'total' => (int) $row->total,
            ])
            ->values();

        $since30Days = Carbon::now()->subDays(30);

        $treeScope = fn ($q) => $q->when(
            $accessibleGardenIds !== null,
            fn ($sub) => $sub->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds))
        );

        $activitiesLast30Days = [
            'measurements' => $treeScope(TreeMeasurement::query())->where('measured_at', '>=', $since30Days)->count(),
            'fertilizations' => $treeScope(FertilizationRecord::query())->where('activity_date', '>=', $since30Days)->count(),
            'sprayings' => $treeScope(SprayingRecord::query())->where('activity_date', '>=', $since30Days)->count(),
            'flowerings' => $treeScope(FloweringRecord::query())->where('flowering_date', '>=', $since30Days->toDateString())->count(),
            'fruit_developments' => $treeScope(FruitDevelopmentRecord::query())->where('observation_date', '>=', $since30Days->toDateString())->count(),
        ];

        $harvestStats = HarvestRecord::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
            ->selectRaw('COUNT(id) as total_records, COALESCE(SUM(fruit_count), 0) as total_fruits, COALESCE(SUM(total_weight_kg), 0) as total_weight_kg')
            ->first();

        return $this->successResponse([
            'territory' => [
                'villages_count' => $villagesCount,
                'blocks_count' => $blocksCount,
                'gardens_count' => $gardensCount,
                'trees_count' => $treesCount,
            ],
            'trees_by_status' => $treesByStatus,
            'trees_by_variety' => $treesByVariety,
            'activities_last_30_days' => $activitiesLast30Days,
            'harvest_totals' => [
                'total_records' => (int) ($harvestStats->total_records ?? 0),
                'total_fruits' => (int) ($harvestStats->total_fruits ?? 0),
                'total_weight_kg' => round((float) ($harvestStats->total_weight_kg ?? 0), 3),
            ],
        ], 'Ringkasan dashboard berhasil dimuat');
    }
}
