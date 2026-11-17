<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgriculturalProduct extends Model
{
    protected $table = 'agricultural_products';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}