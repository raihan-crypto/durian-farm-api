<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Village extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'villages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'district',
        'regency',
        'province',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'village_id');
    }

    public function gardens(): HasManyThrough
    {
        return $this->hasManyThrough(Garden::class, Block::class, 'village_id', 'block_id');
    }
}
