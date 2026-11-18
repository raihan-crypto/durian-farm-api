<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherRecord extends Model
{
    protected $table = 'weather_records';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
