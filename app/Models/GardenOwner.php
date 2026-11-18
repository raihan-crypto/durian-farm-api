<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenOwner extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'garden_owners';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'garden_id',
        'owner_id',
        'ownership_percentage',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function garden(): BelongsTo
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }
}
