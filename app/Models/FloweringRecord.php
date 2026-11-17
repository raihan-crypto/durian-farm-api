<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloweringRecord extends Model
{
    protected $table = 'flowering_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}