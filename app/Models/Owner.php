<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Owner extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'owners';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'owner_type',
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
        return $this->belongsToMany(Garden::class, 'garden_owners', 'owner_id', 'garden_id')
            ->using(GardenOwner::class)
            ->withPivot(['id', 'ownership_percentage', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function gardenOwners(): HasMany
    {
        return $this->hasMany(GardenOwner::class, 'owner_id');
    }
}
