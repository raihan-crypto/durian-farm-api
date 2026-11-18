<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloweringRecord extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'flowering_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'tree_id',
        'flowering_date',
        'flowering_status',
        'flower_count',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'flowering_date' => 'date',
            'flower_count' => 'integer',
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

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id')->where('activity_type', 'flowering');
    }
}
