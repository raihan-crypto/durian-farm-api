<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarvestDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'harvest_details';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'harvest_record_id',
        'fruit_grade',
        'fruit_count',
        'weight_kg',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fruit_count' => 'integer',
            'weight_kg' => 'decimal:3',
        ];
    }

    public function harvestRecord(): BelongsTo
    {
        return $this->belongsTo(HarvestRecord::class, 'harvest_record_id');
    }
}
