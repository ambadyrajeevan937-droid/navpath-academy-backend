<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Services\EnrolmentService;

/**
 * The single answer to "may this student watch this lesson?".
 *
 * Consulted by the lesson API, the DRM licence endpoint and the offline
 * download endpoint. One implementation, so the three cannot disagree.
 */
class LessonPolicy
{
    public function __construct(private readonly EnrolmentService $enrolments) {}

    public function view(User $user, Lesson $lesson): bool
    {
        // Free preview lessons are the acquisition funnel — deliberately open.
        if ($lesson->is_free) {
            return true;
        }

        return $this->enrolments->isEntitled($user, $lesson->course);
    }

    /** Offline download is app-only and stricter: never for free previews. */
    public function download(User $user, Lesson $lesson): bool
    {
        return $this->enrolments->isEntitled($user, $lesson->course);
    }
}
