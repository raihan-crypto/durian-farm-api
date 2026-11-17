<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Village extends Model
{
    protected $table = 'villages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}