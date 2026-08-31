<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = ['attempt_id', 'question_id', 'selected_option', 'is_correct'];
    protected $casts = ['is_correct' => 'boolean'];

    public function attempt(): BelongsTo { return $this->belongsTo(Attempt::class); }
}
