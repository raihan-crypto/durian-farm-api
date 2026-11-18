<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tree extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trees';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'garden_id',
        'variety_id',
        'tree_code',
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
    ];

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
}
