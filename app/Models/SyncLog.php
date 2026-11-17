<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';
}