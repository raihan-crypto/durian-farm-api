<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestSeason extends Model
{
    protected $table = 'harvest_seasons';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
