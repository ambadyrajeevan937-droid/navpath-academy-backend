<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Client\PendingRequest;

/**
 * The single client of the Learnyst API.
 *
 * Nothing else in the codebase — and neither frontend — talks to Learnyst
 * directly. That buys us four things: one contract for web and Android,
 * aggressive caching, credentials that never leave the server, and a future
 * migration that touches one class instead of two applications.
 */
class LearnystService
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $apiKey = '',
    ) {}

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.learnyst.base_url'))
            ->withToken(config('services.learnyst.api_key'))
            ->timeout(10)
            ->retry(3, 200, throw: false);   // transient 5xx / network blips
    }

    /**
     * Catalogue read. Cached for six hours because the catalogue changes rarely
     * and Learnyst rate-limits — but the cache is busted by the webhook, not
     * only by TTL, so a course edit is visible in seconds rather than hours.
     */
    public function courses(): Collection
    {
        return Cache::remember('learnyst.courses', now()->addHours(6), function () {
            $res = $this->client()->get('/v1/courses');

            // Serving stale beats serving nothing: if Learnyst is down, the
            // catalogue keeps rendering from what we last synced to MySQL.
            if ($res->failed()) {
                return \App\Models\Course::query()->where('is_published', true)->get();
            }

            return $res->collect('data');
        });
    }

    /**
     * Mints a short-lived, watermarked playback URL.
     *
     * Never cached, and only ever called AFTER LessonPolicy@view has passed —
     * the policy is the authorisation boundary, this method is just the
     * mechanism.
     */
    public function playbackUrl(Lesson $lesson, User $user): string
    {
        return $this->client()->post("/v1/assets/{$lesson->learnyst_asset_id}/playback", [
            'user_ref'  => $user->learnyst_user_id,
            'ttl'       => 300,                     // 5 minutes
            'watermark' => $user->email,            // forensic, traceable to the account
            'drm'       => 'widevine',
        ])->throw()->json('url');
    }

    /** Pushes an enrolment we have already written locally. */
    public function createEnrolment(User $user, string $learnystCourseId): ?string
    {
        $res = $this->client()->post('/v1/enrolments', [
            'user_ref'   => $user->learnyst_user_id,
            'course_ref' => $learnystCourseId,
        ]);

        // A failure here is recoverable: the nightly ReconcileEnrolments job
        // diffs both sides and repairs drift. We do not block the student's
        // access on a vendor round-trip succeeding.
        return $res->successful() ? $res->json('id') : null;
    }

    /** Fetches a paper for an attempt. Cached per test — questions are static. */
    public function questions(string $learnystTestId): Collection
    {
        return Cache::remember("learnyst.test.$learnystTestId", now()->addDay(),
            fn () => $this->client()->get("/v1/tests/$learnystTestId/questions")->throw()->collect('data'));
    }

    /** Posts a submitted attempt back so Learnyst's reporting stays authoritative. */
    public function submitAttempt(string $learnystTestId, array $payload): void
    {
        $this->client()->post("/v1/tests/$learnystTestId/attempts", $payload);
    }

    /**
     * Verifies a webhook came from Learnyst before we act on it.
     * hash_equals, not ==, to avoid a timing side-channel.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        return hash_equals(
            hash_hmac('sha256', $payload, config('services.learnyst.webhook_secret')),
            $signature
        );
    }
}
