<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherRecord extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'weather_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'monitoring_area_id',
        'garden_id',
        'recorded_at',
        'temperature_c',
        'humidity_percent',
        'rainfall_mm',
        'wind_speed',
        'weather_condition',
        'source',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature_c' => 'decimal:2',
            'humidity_percent' => 'decimal:2',
            'rainfall_mm' => 'decimal:2',
            'wind_speed' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function monitoringArea(): BelongsTo
    {
        return $this->belongsTo(MonitoringArea::class, 'monitoring_area_id');
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }
}
