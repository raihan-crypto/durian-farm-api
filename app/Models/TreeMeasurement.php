<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreeMeasurement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tree_measurements';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'tree_id',
        'measured_at',
        'diameter_cm',
        'height_m',
        'canopy_width_m',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'diameter_cm' => 'decimal:2',
            'height_m' => 'decimal:2',
            'canopy_width_m' => 'decimal:2',
        ];
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class, 'tree_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id')->where('activity_type', 'tree_measurement');
    }
}
