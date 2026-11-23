<?php

namespace App\Models;

use App\Services\TerritoryCodeService;
use App\Services\TerritoryDuplicateGuard;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'blocks';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'village_id',
        'name',
        'block_code',
        'area_hectares',
        'description',
        'approval_status',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Block $block) {
            app(TerritoryDuplicateGuard::class)->assertBlockIsUnique($block);

            if (empty($block->block_code) && $block->village_id) {
                $block->block_code = app(TerritoryCodeService::class)->blockCodeFor($block);
            } elseif ($block->isDirty('village_id') && $block->village_id) {
                $village = Village::withTrashed()->find($block->village_id);
                if ($village && $village->village_code) {
                    $block->block_code = app(TerritoryCodeService::class)->rePrefixBlockCode($block, $village->village_code);
                }
            }

            if (empty($block->approval_status)) {
                $block->approval_status = 'approved';
            }
        });

        static::saved(function (Block $block) {
            if ($block->wasChanged('block_code')) {
                app(TerritoryCodeService::class)->refreshTreesOfBlock($block);
            }
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
