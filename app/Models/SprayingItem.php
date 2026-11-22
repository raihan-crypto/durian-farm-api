<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SprayingItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'spraying_items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'spraying_record_id',
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

    public function sprayingRecord(): BelongsTo
    {
        return $this->belongsTo(SprayingRecord::class, 'spraying_record_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AgriculturalProduct::class, 'product_id');
    }
}
