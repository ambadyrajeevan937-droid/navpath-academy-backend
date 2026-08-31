<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Order;
use App\Models\User;

/**
 * Entitlement logic, in one place.
 *
 * "Is this student entitled to this course?" is asked by the API, the DRM
 * licence endpoint, the offline-download endpoint and the test engine. Keeping
 * the answer here is what stops those four from drifting apart — which is the
 * usual way access-control holes appear.
 */
class EnrolmentService
{
    public function __construct(private readonly LearnystService $learnyst) {}

    public function grant(User $user, Course $course, ?Order $order = null): Enrolment
    {
        $enrolment = Enrolment::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'order_id'   => $order?->id,
                'status'     => 'active',
                'starts_at'  => now(),
                'expires_at' => now()->addDays($course->validity_days),
            ]
        );

        // Best-effort push. If Learnyst is unreachable the student still has
        // access; ReconcileEnrolments repairs the link overnight.
        if ($id = $this->learnyst->createEnrolment($user, $course->learnyst_course_id)) {
            $enrolment->update(['learnyst_enrolment_id' => $id]);
        }

        return $enrolment;
    }

    /** The one definition of "currently entitled". */
    public function isEntitled(User $user, Course $course): bool
    {
        return Enrolment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function revoke(Enrolment $enrolment, string $reason = 'refunded'): void
    {
        $enrolment->update(['status' => $reason]);
    }
}
