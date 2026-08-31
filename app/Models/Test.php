<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    protected $fillable = ['course_id', 'learnyst_test_id', 'title', 'kind',
                           'question_count', 'duration_min', 'max_attempts',
                           'opens_at', 'closes_at'];
    protected $casts = ['opens_at' => 'datetime', 'closes_at' => 'datetime'];

    public function course(): BelongsTo  { return $this->belongsTo(Course::class); }
    public function attempts(): HasMany  { return $this->hasMany(Attempt::class); }

    public function isOpen(): bool
    {
        return (! $this->opens_at  || $this->opens_at->isPast())
            && (! $this->closes_at || $this->closes_at->isFuture());
    }
}
