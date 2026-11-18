<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreeQrCode extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'tree_qr_codes';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'tree_id',
        'qr_code',
        'public_token',
        'generated_at',
        'is_active',
        'public_fields',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'is_active' => 'boolean',
            'public_fields' => 'array',
        ];
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class, 'tree_id');
    }
}
