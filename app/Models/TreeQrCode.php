<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreeQrCode extends Model
{
    protected $table = 'tree_qr_codes';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';
}
