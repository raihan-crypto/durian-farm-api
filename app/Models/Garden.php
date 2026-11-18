<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garden extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'gardens';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'block_id',
        'name',
        'area_hectares',
        'address',
        'latitude',
        'longitude',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
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

    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class, 'garden_id');
    }
}
