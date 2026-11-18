<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HarvestRecord extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'harvest_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'garden_id',
        'tree_id',
        'fruit_count',
        'total_weight_kg',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fruit_count' => 'integer',
            'total_weight_kg' => 'decimal:3',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(HarvestSession::class, 'session_id');
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class, 'tree_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(HarvestDetail::class, 'harvest_record_id');
    }
}
