<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityPhoto extends Model
{
    protected $table = 'activity_photos';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
