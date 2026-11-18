<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprayingRecord extends Model
{
    protected $table = 'spraying_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
