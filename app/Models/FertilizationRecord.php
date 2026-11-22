<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FertilizationRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'fertilization_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tree_id',
        'activity_date',
        'method',
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
        return $this->hasMany(FertilizationItem::class, 'fertilization_record_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id')->where('activity_type', 'fertilization');
    }
}
