<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'owners';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_type',
        'name',
        'phone',
        'address',
    ];

    public function gardens(): BelongsToMany
    {
        return $this->belongsToMany(Garden::class, 'garden_owners', 'owner_id', 'garden_id')
            ->withPivot(['id', 'ownership_percentage', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function gardenOwners(): HasMany
    {
        return $this->hasMany(GardenOwner::class, 'owner_id');
    }
}
