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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Cache duration for dashboard aggregated summaries (5 minutes).
     */
    public const DASHBOARD_CACHE_TTL_SECONDS = 300;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Aggregated plantation dashboard summary scoped to user's territory access (SRS 9.1 & 11.3).
     * Employs tenant-scoped caching and optimized index-backed aggregation queries.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user?->organization_id ?? 'global';

        $accessibleVillageIds = $this->territoryScope->getAccessibleVillageIds($user);
        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($user);
        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($user);

        $mutationTime = (int) Cache::get("org:{$orgId}:last_mutation_time", 0);
        $scopeHash = md5(json_encode([$accessibleVillageIds, $accessibleBlockIds, $accessibleGardenIds]));
        $cacheKey = "org:{$orgId}:dashboard:summary:{$scopeHash}:{$mutationTime}";

        if ($request->boolean('fresh')) {
            Cache::forget($cacheKey);
        }

        $summaryData = Cache::remember($cacheKey, self::DASHBOARD_CACHE_TTL_SECONDS, function () use (
            $accessibleVillageIds,
            $accessibleBlockIds,
            $accessibleGardenIds
        ) {
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

            // Optimized activity query helper avoiding deep nested subqueries
            $treeIdSub = $accessibleGardenIds !== null
                ? Tree::query()->whereIn('garden_id', $accessibleGardenIds)->select('id')
                : null;

            $scopeByTree = fn ($q) => $treeIdSub !== null ? $q->whereIn('tree_id', $treeIdSub) : $q;

            $activitiesLast30Days = [
                'measurements' => $scopeByTree(TreeMeasurement::query())->where('measured_at', '>=', $since30Days)->count(),
                'fertilizations' => $scopeByTree(FertilizationRecord::query())->where('activity_date', '>=', $since30Days)->count(),
                'sprayings' => $scopeByTree(SprayingRecord::query())->where('activity_date', '>=', $since30Days)->count(),
                'flowerings' => $scopeByTree(FloweringRecord::query())->where('flowering_date', '>=', $since30Days->toDateString())->count(),
                'fruit_developments' => $scopeByTree(FruitDevelopmentRecord::query())->where('observation_date', '>=', $since30Days->toDateString())->count(),
            ];

            $harvestStats = HarvestRecord::query()
                ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
                ->selectRaw('COUNT(id) as total_records, COALESCE(SUM(fruit_count), 0) as total_fruits, COALESCE(SUM(total_weight_kg), 0) as total_weight_kg')
                ->first();

            return [
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
            ];
        });

        return $this->successResponse($summaryData, 'Ringkasan dashboard berhasil dimuat');
    }
}
