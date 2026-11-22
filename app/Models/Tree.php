<?php

namespace App\Models;

use App\Services\TreeIdentityNumberService;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tree extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'trees';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Tree statuses with their Indonesian labels (SRS 4.4).
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'unproductive' => 'Tidak produktif',
        'sick' => 'Sakit',
        'monitoring' => 'Dalam pemantauan',
        'dead' => 'Mati',
        'removed' => 'Ditebang',
    ];

    /**
     * Activity type used in activity_photos for tree photos.
     */
    public const PHOTO_ACTIVITY_TYPE = 'tree';

    protected $fillable = [
        'garden_id',
        'variety_id',
        'tree_code',
        'tree_identity_number',
        'tree_name',
        'planted_at',
        'planting_age_months',
        'initial_diameter_cm',
        'initial_height_m',
        'initial_canopy_width_m',
        'latitude',
        'longitude',
        'status',
        'notes',
        'photo_path',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tree $tree) {
            // Auto generate or recalculate NIK if missing or if garden/variety changed
            if (empty($tree->tree_identity_number) || $tree->isDirty(['garden_id', 'variety_id'])) {
                $service = app(TreeIdentityNumberService::class);
                $tree->tree_identity_number = $service->generateForTree($tree);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
            'planting_age_months' => 'integer',
            'initial_diameter_cm' => 'decimal:2',
            'initial_height_m' => 'decimal:2',
            'initial_canopy_width_m' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(DurianVariety::class, 'variety_id');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(TreeQrCode::class, 'tree_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(TreeMeasurement::class, 'tree_id')->orderBy('measured_at', 'desc');
    }

    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(TreeMeasurement::class, 'tree_id')->latestOfMany('measured_at');
    }

    public function fertilizationRecords(): HasMany
    {
        return $this->hasMany(FertilizationRecord::class, 'tree_id')->orderBy('activity_date', 'desc');
    }

    public function sprayingRecords(): HasMany
    {
        return $this->hasMany(SprayingRecord::class, 'tree_id')->orderBy('activity_date', 'desc');
    }

    public function floweringRecords(): HasMany
    {
        return $this->hasMany(FloweringRecord::class, 'tree_id')->orderBy('flowering_date', 'desc');
    }

    public function fruitDevelopmentRecords(): HasMany
    {
        return $this->hasMany(FruitDevelopmentRecord::class, 'tree_id')->orderBy('observation_date', 'desc');
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class, 'tree_id')->orderBy('created_at', 'desc');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id')
            ->where('activity_type', self::PHOTO_ACTIVITY_TYPE)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the human readable status label.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    /**
     * Get the tree age in months based on planting date or recorded planting age.
     */
    public function ageInMonths(): ?int
    {
        if ($this->planted_at !== null) {
            return (int) $this->planted_at->diffInMonths(now());
        }

        return $this->planting_age_months;
    }
}
