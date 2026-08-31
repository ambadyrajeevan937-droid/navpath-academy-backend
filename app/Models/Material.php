<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = ['course_id', 'module_id', 'title', 'storage_key', 'pages', 'is_downloadable'];
}
