<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    protected $fillable = ['course_id', 'module_id', 'title', 'duration_sec',
                           'learnyst_asset_id', 'is_free', 'position'];
    protected $casts = ['is_free' => 'boolean'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
