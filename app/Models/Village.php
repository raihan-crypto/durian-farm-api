<?php

namespace App\Models;

use App\Support\BanyumasRegionData;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Village extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'villages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'village_code',
        'district',
        'regency',
        'province',
    ];

    protected static function booted(): void
    {
        static::creating(function (Village $village) {
            if (empty($village->village_code)) {
                $code = BanyumasRegionData::findVillageCode($village->name, $village->district);
                if ($code) {
                    $village->village_code = $code;
                } else {
                    $count = static::withTrashed()->where('district', $village->district)->count();
                    $village->village_code = sprintf('%02d', $count + 1);
                }
            }
        });
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'village_id');
    }

    public function gardens(): HasManyThrough
    {
        return $this->hasManyThrough(Garden::class, Block::class, 'village_id', 'block_id');
    }
}
