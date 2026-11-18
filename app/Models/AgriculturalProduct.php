<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriculturalProduct extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'agricultural_products';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category_id',
        'name',
        'brand',
        'active_ingredient',
        'unit',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function fertilizationItems(): HasMany
    {
        return $this->hasMany(FertilizationItem::class, 'product_id');
    }

    public function sprayingItems(): HasMany
    {
        return $this->hasMany(SprayingItem::class, 'product_id');
    }
}
