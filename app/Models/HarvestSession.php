<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HarvestSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'harvest_sessions';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'season_id',
        'garden_id',
        'harvest_date',
        'session_name',
        'weather_condition',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'harvest_date' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(HarvestSeason::class, 'season_id');
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(HarvestRecord::class, 'session_id');
    }
}
