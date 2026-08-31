<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    /** IMU CET marking: +1 correct, −0.25 wrong, 0 skipped. */
    public const MARK_CORRECT = 1.0;
    public const MARK_WRONG   = -0.25;

    protected $fillable = ['user_id', 'test_id', 'started_at', 'submitted_at',
                           'score', 'max_score', 'correct', 'wrong', 'skipped',
                           'section_breakdown'];
    protected $casts = [
        'started_at'        => 'datetime',
        'submitted_at'      => 'datetime',
        'score'             => 'decimal:2',
        'section_breakdown' => 'array',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function test(): BelongsTo   { return $this->belongsTo(Test::class); }
    public function answers(): HasMany  { return $this->hasMany(Answer::class); }

    /**
     * Scoring is computed server-side from stored answers — never accepted from
     * the client, which would make every score forgeable.
     */
    public function computeScore(): float
    {
        return max(0, round(
            $this->correct * self::MARK_CORRECT + $this->wrong * self::MARK_WRONG, 2
        ));
    }

    public function accuracy(): int
    {
        $attempted = $this->correct + $this->wrong;

        return $attempted ? (int) round($this->correct / $attempted * 100) : 0;
    }
}
