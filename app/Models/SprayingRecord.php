<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SprayingRecord extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'spraying_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tree_id',
        'activity_date',
        'spraying_method',
        'dosage_notes',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'datetime',
        ];
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class, 'tree_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SprayingItem::class, 'spraying_record_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id')->where('activity_type', 'spraying');
    }
}
