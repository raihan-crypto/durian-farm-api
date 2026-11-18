<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Block extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'blocks';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'village_id',
        'name',
        'area_hectares',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id');
    }

    public function gardens(): HasMany
    {
        return $this->hasMany(Garden::class, 'block_id');
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class, 'block_farmers', 'block_id', 'farmer_id')
            ->withPivot(['id', 'assignment_date', 'end_date'])
            ->withTimestamps();
    }

    public function blockFarmers(): HasMany
    {
        return $this->hasMany(BlockFarmer::class, 'block_id');
    }

    public function trees(): HasManyThrough
    {
        return $this->hasManyThrough(Tree::class, Garden::class, 'block_id', 'garden_id');
    }
}
