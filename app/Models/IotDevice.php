<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    protected $table = 'iot_devices';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}