<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FertilizationRecord extends Model
{
    protected $table = 'fertilization_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}