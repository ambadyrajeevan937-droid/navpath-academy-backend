<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrolment extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'order_id', 'status',
        'starts_at', 'expires_at', 'learnyst_enrolment_id',
    ];
    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active')
                 ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
