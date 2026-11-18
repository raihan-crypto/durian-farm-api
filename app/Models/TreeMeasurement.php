<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreeMeasurement extends Model
{
    protected $table = 'tree_measurements';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
