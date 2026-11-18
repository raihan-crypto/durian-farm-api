<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GardenFarmer extends Model
{
    protected $table = 'garden_farmers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
