<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockFarmer extends Model
{
    protected $table = 'block_farmers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}