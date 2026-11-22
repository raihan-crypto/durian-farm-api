<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FertilizationItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'fertilization_items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'fertilization_record_id',
        'product_id',
        'quantity',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function fertilizationRecord(): BelongsTo
    {
        return $this->belongsTo(FertilizationRecord::class, 'fertilization_record_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AgriculturalProduct::class, 'product_id');
    }
}
