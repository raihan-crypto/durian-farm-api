<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farmer extends Model
{
    protected $table = 'farmers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}