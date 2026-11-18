<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestDetail extends Model
{
    protected $table = 'harvest_details';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
