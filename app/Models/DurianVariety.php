<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DurianVariety extends Model
{
    protected $table = 'durian_varieties';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}