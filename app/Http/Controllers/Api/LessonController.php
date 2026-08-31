<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Progress;
use App\Services\LearnystService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin by design: validate, authorise, delegate, return. The interesting logic
 * lives in the policy and the service, where both the API and the queued jobs
 * can reach it.
 */
class LessonController extends Controller
{
    public function __construct(private readonly LearnystService $learnyst) {}

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        // One authorisation boundary, shared with the DRM and download endpoints.
        $this->authorize('view', $lesson);

        return response()->json([
            'id'       => $lesson->id,
            'title'    => $lesson->title,
            'duration' => $lesson->duration_sec,
            // Minted per request, 5-minute TTL, watermarked with the student's identity.
            'playback' => $this->learnyst->playbackUrl($lesson, $request->user()),
        ]);
    }

    /**
     * Progress writes are idempotent and monotonic: a client that replays an
     * old offline event must never move a student's position backwards.
     */
    public function progress(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        $data = $request->validate([
            'watched_sec' => ['required', 'integer', 'min:0', 'max:'.$lesson->duration_sec],
            'completed'   => ['boolean'],
        ]);

        $progress = Progress::firstOrNew([
            'user_id'   => $request->user()->id,
            'lesson_id' => $lesson->id,
        ]);

        $progress->watched_sec  = max($progress->watched_sec ?? 0, $data['watched_sec']);
        $progress->completed_at = ($data['completed'] ?? false)
            ? ($progress->completed_at ?? now())
            : $progress->completed_at;
        $progress->save();

        return response()->json(['ok' => true, 'watched_sec' => $progress->watched_sec]);
    }
}
