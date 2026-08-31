<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['user_id', 'device_id', 'platform', 'last_seen_at', 'revoked_at'];
}
