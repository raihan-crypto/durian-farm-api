<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HarvestSeason extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'harvest_seasons';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'garden_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(HarvestSession::class, 'season_id');
    }
}
