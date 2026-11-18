<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GardenOwner extends Model
{
    protected $table = 'garden_owners';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
