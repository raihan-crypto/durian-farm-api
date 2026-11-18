<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
    protected $table = 'gardens';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
