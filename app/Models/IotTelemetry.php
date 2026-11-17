<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotTelemetry extends Model
{
    protected $table = 'iot_telemetry';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';
}