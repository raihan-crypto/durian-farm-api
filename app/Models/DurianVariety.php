<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DurianVariety extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'durian_varieties';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'origin',
        'description',
    ];

    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class, 'variety_id');
    }
}
