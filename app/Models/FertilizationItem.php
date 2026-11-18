<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FertilizationItem extends Model
{
    protected $table = 'fertilization_items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
