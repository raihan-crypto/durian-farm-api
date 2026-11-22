<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Garden extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'gardens';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'block_id',
        'name',
        'garden_code',
        'area_hectares',
        'address',
        'latitude',
        'longitude',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Garden $garden) {
            if (empty($garden->garden_code) && $garden->block_id) {
                $count = static::withTrashed()->where('block_id', $garden->block_id)->count();
                $garden->garden_code = sprintf('%02d', $count + 1);
            }
            if (empty($garden->status)) {
                $garden->status = 'active';
            }
        });
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'garden_owners', 'garden_id', 'owner_id')
            ->withPivot(['id', 'ownership_percentage', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function gardenOwners(): HasMany
    {
        return $this->hasMany(GardenOwner::class, 'garden_id');
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class, 'garden_farmers', 'garden_id', 'farmer_id')
            ->withPivot(['id', 'assignment_date', 'end_date'])
            ->withTimestamps();
    }

    public function gardenFarmers(): HasMany
    {
        return $this->hasMany(GardenFarmer::class, 'garden_id');
    }

    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class, 'garden_id');
    }

    public function monitoringAreas(): HasMany
    {
        return $this->hasMany(MonitoringArea::class, 'garden_id');
    }

    public function harvestSeasons(): HasMany
    {
        return $this->hasMany(HarvestSeason::class, 'garden_id');
    }

    public function harvestSessions(): HasMany
    {
        return $this->hasMany(HarvestSession::class, 'garden_id');
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class, 'garden_id');
    }

    public function fertilizationRecords(): HasManyThrough
    {
        return $this->hasManyThrough(FertilizationRecord::class, Tree::class, 'garden_id', 'tree_id');
    }

    public function sprayingRecords(): HasManyThrough
    {
        return $this->hasManyThrough(SprayingRecord::class, Tree::class, 'garden_id', 'tree_id');
    }

    public function treeMeasurements(): HasManyThrough
    {
        return $this->hasManyThrough(TreeMeasurement::class, Tree::class, 'garden_id', 'tree_id');
    }

    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class, 'garden_id');
    }
}
