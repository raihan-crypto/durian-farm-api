<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprayingItem extends Model
{
    protected $table = 'spraying_items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}