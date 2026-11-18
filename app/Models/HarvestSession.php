<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestSession extends Model
{
    protected $table = 'harvest_sessions';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
