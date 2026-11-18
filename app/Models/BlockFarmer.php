<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockFarmer extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'block_farmers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'block_id',
        'farmer_id',
        'assignment_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'assignment_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }
}
