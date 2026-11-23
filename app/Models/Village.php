<?php

namespace App\Models;

use App\Services\TerritoryCodeService;
use App\Services\TerritoryDuplicateGuard;
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
        static::saving(function (Village $village) {
            app(TerritoryDuplicateGuard::class)->assertVillageIsUnique($village);

            if (empty($village->village_code) || $village->isDirty(['name', 'district'])) {
                $village->village_code = app(TerritoryCodeService::class)->villageCodeFor($village);
            }
        });

        static::saved(function (Village $village) {
            if ($village->wasChanged('village_code')) {
                app(TerritoryCodeService::class)->cascadeVillage($village);
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
