<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IotDevice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_devices';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'monitoring_area_id',
        'device_code',
        'device_name',
        'device_type',
        'manufacturer',
        'installation_date',
        'last_seen_at',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'last_seen_at' => 'datetime',
        ];
    }

    public function monitoringArea(): BelongsTo
    {
        return $this->belongsTo(MonitoringArea::class, 'monitoring_area_id');
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(IotTelemetry::class, 'device_id')->orderBy('recorded_at', 'desc');
    }
}
