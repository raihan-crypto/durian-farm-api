<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HarvestReportController extends Controller
{
    use ApiResponse;

    /**
     * Allowed grouping dimensions (SRS 6.5).
     *
     * @var array<string, array{id: string, label: string}>
     */
    private const GROUP_MAP = [
        'tree' => ['id' => 'trees.id', 'label' => 'trees.tree_code'],
        'garden' => ['id' => 'gardens.id', 'label' => 'gardens.name'],
        'block' => ['id' => 'blocks.id', 'label' => 'blocks.name'],
        'village' => ['id' => 'villages.id', 'label' => 'villages.name'],
        'variety' => ['id' => 'durian_varieties.id', 'label' => 'durian_varieties.name'],
        'season' => ['id' => 'harvest_seasons.id', 'label' => 'harvest_seasons.name'],
        'session' => ['id' => 'harvest_sessions.id', 'label' => 'harvest_sessions.session_name'],
    ];

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Harvest productivity report with multi-dimensional filters and grouping (SRS 6.5).
     */
    public function summary(Request $request): JsonResponse
    {
        $groupBy = $request->string('group_by', 'garden')->toString();
        if (! isset(self::GROUP_MAP[$groupBy])) {
            return $this->errorResponse(
                'Parameter group_by tidak valid. Pilihan: '.implode(', ', array_keys(self::GROUP_MAP)),
                422
            );
        }

        $baseQuery = $this->buildFilteredQuery($request);

        $totalsRow = (clone $baseQuery)
            ->selectRaw('COUNT(harvest_records.id) as total_records')
            ->selectRaw('COUNT(DISTINCT harvest_records.tree_id) as total_trees_harvested')
            ->selectRaw('COALESCE(SUM(harvest_records.fruit_count), 0) as total_fruits')
            ->selectRaw('COALESCE(SUM(harvest_records.total_weight_kg), 0) as total_weight_kg')
            ->first();

        $totalFruits = (int) ($totalsRow->total_fruits ?? 0);
        $totalWeight = round((float) ($totalsRow->total_weight_kg ?? 0), 3);

        $groupCols = self::GROUP_MAP[$groupBy];

        $breakdownRows = (clone $baseQuery)
            ->selectRaw("{$groupCols['id']} as group_id")
            ->selectRaw("{$groupCols['label']} as group_label")
            ->selectRaw('COUNT(harvest_records.id) as total_records')
            ->selectRaw('COUNT(DISTINCT harvest_records.tree_id) as total_trees')
            ->selectRaw('COALESCE(SUM(harvest_records.fruit_count), 0) as total_fruits')
            ->selectRaw('COALESCE(SUM(harvest_records.total_weight_kg), 0) as total_weight_kg')
            ->groupBy($groupCols['id'], $groupCols['label'])
            ->orderByDesc('total_weight_kg')
            ->get()
            ->map(function ($row) {
                $fruits = (int) $row->total_fruits;
                $weight = round((float) $row->total_weight_kg, 3);

                return [
                    'id' => $row->group_id,
                    'label' => $row->group_label ?? 'Tidak ditentukan',
                    'total_records' => (int) $row->total_records,
                    'total_trees' => (int) $row->total_trees,
                    'total_fruits' => $fruits,
                    'total_weight_kg' => $weight,
                    'average_weight_per_fruit_kg' => $fruits > 0 ? round($weight / $fruits, 3) : 0.0,
                ];
            })
            ->values();

        $filteredRecordIds = (clone $baseQuery)->pluck('harvest_records.id');

        $gradeBreakdown = $filteredRecordIds->isEmpty()
            ? collect()
            : DB::table('harvest_details')
                ->whereIn('harvest_record_id', $filteredRecordIds)
                ->selectRaw('fruit_grade')
                ->selectRaw('COALESCE(SUM(fruit_count), 0) as total_fruits')
                ->selectRaw('COALESCE(SUM(weight_kg), 0) as total_weight_kg')
                ->groupBy('fruit_grade')
                ->orderByDesc('total_weight_kg')
                ->get()
                ->map(function ($row) {
                    $fruits = (int) $row->total_fruits;
                    $weight = round((float) $row->total_weight_kg, 3);

                    return [
                        'fruit_grade' => $row->fruit_grade ?? 'Tanpa Grade',
                        'total_fruits' => $fruits,
                        'total_weight_kg' => $weight,
                        'average_weight_per_fruit_kg' => $fruits > 0 ? round($weight / $fruits, 3) : 0.0,
                    ];
                })
                ->values();

        return $this->successResponse([
            'group_by' => $groupBy,
            'totals' => [
                'total_records' => (int) ($totalsRow->total_records ?? 0),
                'total_trees_harvested' => (int) ($totalsRow->total_trees_harvested ?? 0),
                'total_fruits' => $totalFruits,
                'total_weight_kg' => $totalWeight,
                'average_weight_per_fruit_kg' => $totalFruits > 0 ? round($totalWeight / $totalFruits, 3) : 0.0,
            ],
            'breakdown' => $breakdownRows,
            'grade_breakdown' => $gradeBreakdown,
        ], 'Laporan ringkasan panen berhasil dimuat');
    }

    /**
     * Compare harvest productivity between two date periods (SRS 6.5).
     */
    public function comparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_a_from' => ['required', 'date'],
            'period_a_to' => ['required', 'date', 'after_or_equal:period_a_from'],
            'period_b_from' => ['required', 'date'],
            'period_b_to' => ['required', 'date', 'after_or_equal:period_b_from'],
        ]);

        $periodA = $this->summarizePeriod($request, $validated['period_a_from'], $validated['period_a_to']);
        $periodB = $this->summarizePeriod($request, $validated['period_b_from'], $validated['period_b_to']);

        $fruitDiff = $periodB['total_fruits'] - $periodA['total_fruits'];
        $weightDiff = round($periodB['total_weight_kg'] - $periodA['total_weight_kg'], 3);

        $fruitGrowthPercent = $periodA['total_fruits'] > 0
            ? round(($fruitDiff / $periodA['total_fruits']) * 100, 2)
            : null;
        $weightGrowthPercent = $periodA['total_weight_kg'] > 0
            ? round(($weightDiff / $periodA['total_weight_kg']) * 100, 2)
            : null;

        return $this->successResponse([
            'period_a' => array_merge([
                'from' => $validated['period_a_from'],
                'to' => $validated['period_a_to'],
            ], $periodA),
            'period_b' => array_merge([
                'from' => $validated['period_b_from'],
                'to' => $validated['period_b_to'],
            ], $periodB),
            'comparison' => [
                'fruit_diff' => $fruitDiff,
                'weight_diff_kg' => $weightDiff,
                'fruit_growth_percent' => $fruitGrowthPercent,
                'weight_growth_percent' => $weightGrowthPercent,
            ],
        ], 'Laporan perbandingan panen antarperiode berhasil dimuat');
    }

    /**
     * Export filtered harvest records as a safe CSV file (SRS 9.5).
     */
    public function export(Request $request): Response
    {
        $rows = $this->buildFilteredQuery($request)
            ->select([
                'harvest_sessions.harvest_date',
                'harvest_seasons.name as season_name',
                'harvest_sessions.session_name',
                'villages.name as village_name',
                'blocks.name as block_name',
                'gardens.name as garden_name',
                'trees.tree_code',
                'trees.tree_name',
                'durian_varieties.name as variety_name',
                'harvest_records.fruit_count',
                'harvest_records.total_weight_kg',
                'harvest_records.notes',
            ])
            ->orderByDesc('harvest_sessions.harvest_date')
            ->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'Tanggal Panen',
            'Musim Panen',
            'Sesi Panen',
            'Desa',
            'Blok',
            'Kebun',
            'Kode Pohon',
            'Nama Pohon',
            'Varietas',
            'Jumlah Buah',
            'Total Berat (kg)',
            'Rata-rata Berat/Buah (kg)',
            'Catatan',
        ]);

        foreach ($rows as $row) {
            $fruits = (int) $row->fruit_count;
            $weight = round((float) $row->total_weight_kg, 3);
            $avg = $fruits > 0 ? round($weight / $fruits, 3) : 0.0;

            fputcsv($stream, [
                $this->sanitizeCsvCell((string) ($row->harvest_date ?? '')),
                $this->sanitizeCsvCell((string) ($row->season_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->session_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->village_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->block_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->garden_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->tree_code ?? '')),
                $this->sanitizeCsvCell((string) ($row->tree_name ?? '')),
                $this->sanitizeCsvCell((string) ($row->variety_name ?? '')),
                $fruits,
                $weight,
                $avg,
                $this->sanitizeCsvCell((string) ($row->notes ?? '')),
            ]);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-panen-durian.csv"',
        ]);
    }

    /**
     * Build the base joined query for harvest reporting with territory scoping and filters.
     */
    protected function buildFilteredQuery(Request $request, bool $applyDateFilters = true): Builder
    {
        $query = DB::table('harvest_records')
            ->join('harvest_sessions', 'harvest_records.session_id', '=', 'harvest_sessions.id')
            ->join('harvest_seasons', 'harvest_sessions.season_id', '=', 'harvest_seasons.id')
            ->join('trees', 'harvest_records.tree_id', '=', 'trees.id')
            ->join('gardens', 'harvest_records.garden_id', '=', 'gardens.id')
            ->join('blocks', 'gardens.block_id', '=', 'blocks.id')
            ->join('villages', 'blocks.village_id', '=', 'villages.id')
            ->leftJoin('durian_varieties', 'trees.variety_id', '=', 'durian_varieties.id');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            if (empty($accessibleGardenIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('harvest_records.garden_id', $accessibleGardenIds);
            }
        }

        if ($applyDateFilters && $request->filled('from_date')) {
            $query->whereDate('harvest_sessions.harvest_date', '>=', $request->string('from_date')->toString());
        }

        if ($applyDateFilters && $request->filled('to_date')) {
            $query->whereDate('harvest_sessions.harvest_date', '<=', $request->string('to_date')->toString());
        }

        if ($request->filled('season_id')) {
            $query->where('harvest_seasons.id', $request->string('season_id')->toString());
        }

        if ($request->filled('session_id')) {
            $query->where('harvest_sessions.id', $request->string('session_id')->toString());
        }

        if ($request->filled('village_id')) {
            $query->where('villages.id', $request->string('village_id')->toString());
        }

        if ($request->filled('block_id')) {
            $query->where('blocks.id', $request->string('block_id')->toString());
        }

        if ($request->filled('garden_id')) {
            $query->where('gardens.id', $request->string('garden_id')->toString());
        }

        if ($request->filled('variety_id')) {
            $query->where('durian_varieties.id', $request->string('variety_id')->toString());
        }

        if ($request->filled('tree_id')) {
            $query->where('trees.id', $request->string('tree_id')->toString());
        }

        return $query;
    }

    /**
     * Aggregate totals for a specific date period.
     *
     * @return array{total_records: int, total_trees_harvested: int, total_fruits: int, total_weight_kg: float, average_weight_per_fruit_kg: float}
     */
    protected function summarizePeriod(Request $request, string $fromDate, string $toDate): array
    {
        $row = $this->buildFilteredQuery($request, false)
            ->whereDate('harvest_sessions.harvest_date', '>=', $fromDate)
            ->whereDate('harvest_sessions.harvest_date', '<=', $toDate)
            ->selectRaw('COUNT(harvest_records.id) as total_records')
            ->selectRaw('COUNT(DISTINCT harvest_records.tree_id) as total_trees_harvested')
            ->selectRaw('COALESCE(SUM(harvest_records.fruit_count), 0) as total_fruits')
            ->selectRaw('COALESCE(SUM(harvest_records.total_weight_kg), 0) as total_weight_kg')
            ->first();

        $fruits = (int) ($row->total_fruits ?? 0);
        $weight = round((float) ($row->total_weight_kg ?? 0), 3);

        return [
            'total_records' => (int) ($row->total_records ?? 0),
            'total_trees_harvested' => (int) ($row->total_trees_harvested ?? 0),
            'total_fruits' => $fruits,
            'total_weight_kg' => $weight,
            'average_weight_per_fruit_kg' => $fruits > 0 ? round($weight / $fruits, 3) : 0.0,
        ];
    }

    /**
     * Mitigate CSV formula injection in spreadsheet software.
     */
    protected function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
