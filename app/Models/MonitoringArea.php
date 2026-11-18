<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringArea extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'monitoring_areas';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'garden_id',
        'name',
        'description',
        'latitude',
        'longitude',
        'area_hectares',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'area_hectares' => 'decimal:4',
        ];
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class, 'monitoring_area_id');
    }

    public function iotDevices(): HasMany
    {
        return $this->hasMany(IotDevice::class, 'monitoring_area_id');
    }
}
