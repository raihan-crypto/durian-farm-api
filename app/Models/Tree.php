<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tree extends Model
{
    protected $table = 'trees';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
