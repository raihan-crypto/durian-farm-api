<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotTelemetry extends Model
{
    use HasFactory;

    protected $table = 'iot_telemetry';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    const UPDATED_AT = null;

    protected $fillable = [
        'device_id',
        'recorded_at',
        'temperature_c',
        'humidity_percent',
        'soil_moisture_percent',
        'rainfall_mm',
        'battery_percent',
        'additional_data',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature_c' => 'decimal:2',
            'humidity_percent' => 'decimal:2',
            'soil_moisture_percent' => 'decimal:2',
            'rainfall_mm' => 'decimal:2',
            'battery_percent' => 'decimal:2',
            'additional_data' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'device_id');
    }
}
