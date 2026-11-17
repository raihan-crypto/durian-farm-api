<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringArea extends Model
{
    protected $table = 'monitoring_areas';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}