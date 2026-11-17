<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestRecord extends Model
{
    protected $table = 'harvest_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}