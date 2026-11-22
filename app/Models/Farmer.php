<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farmer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'farmers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gardens(): BelongsToMany
    {
        return $this->belongsToMany(Garden::class, 'garden_farmers', 'farmer_id', 'garden_id')
            ->withPivot(['id', 'assignment_date', 'end_date'])
            ->withTimestamps();
    }

    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class, 'block_farmers', 'farmer_id', 'block_id')
            ->withPivot(['id', 'assignment_date', 'end_date'])
            ->withTimestamps();
    }

    public function gardenFarmers(): HasMany
    {
        return $this->hasMany(GardenFarmer::class, 'farmer_id');
    }

    public function blockFarmers(): HasMany
    {
        return $this->hasMany(BlockFarmer::class, 'farmer_id');
    }
}
