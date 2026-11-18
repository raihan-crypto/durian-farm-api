<?php

namespace App\Services;

use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\HarvestRecord;
use App\Models\HarvestSession;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use Illuminate\Database\Eloquent\Model;

class ActivityTargetResolver
{
    /**
     * Map of supported activity_type values to their Eloquent model classes.
     *
     * @var array<string, class-string<Model>>
     */
    public const SUPPORTED_TYPES = [
        'tree' => Tree::class,
        'tree_measurement' => TreeMeasurement::class,
        'fertilization' => FertilizationRecord::class,
        'spraying' => SprayingRecord::class,
        'flowering' => FloweringRecord::class,
        'fruit_development' => FruitDevelopmentRecord::class,
        'harvest' => HarvestRecord::class,
        'harvest_session' => HarvestSession::class,
    ];

    /**
     * Get all allowed activity_type keys.
     *
     * @return array<int, string>
     */
    public function allowedTypes(): array
    {
        return array_keys(self::SUPPORTED_TYPES);
    }

    /**
     * Resolve a polymorphic (activity_type, activity_id) target and return
     * both the target model and its owning garden_id for territory authorization.
     *
     * @return array{model: Model, garden_id: string}|null
     */
    public function resolve(string $activityType, string $activityId): ?array
    {
        if (! isset(self::SUPPORTED_TYPES[$activityType])) {
            return null;
        }

        $modelClass = self::SUPPORTED_TYPES[$activityType];

        if (in_array($activityType, ['tree', 'harvest', 'harvest_session'], true)) {
            $model = $modelClass::query()->find($activityId);

            if (! $model || ! $model->garden_id) {
                return null;
            }

            return [
                'model' => $model,
                'garden_id' => (string) $model->garden_id,
            ];
        }

        $model = $modelClass::query()->with('tree:id,garden_id')->find($activityId);

        if (! $model || ! $model->tree || ! $model->tree->garden_id) {
            return null;
        }

        return [
            'model' => $model,
            'garden_id' => (string) $model->tree->garden_id,
        ];
    }
}
