<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Razorpay orders and verification.
 *
 * The rule this class exists to enforce: the client is never trusted. The
 * amount is computed here from the course record, and an enrolment is written
 * only after the HMAC signature validates. Most real-world payment bugs in this
 * category are a missing version of one of those two checks.
 */
class PaymentService
{
    private const GST_RATE = 0.18;

    public function __construct(private readonly EnrolmentService $enrolments) {}

    /** Creates an order with a SERVER-SIDE amount. The client sends a course, not a price. */
    public function createOrder(User $user, Course $course, ?string $coupon = null): Order
    {
        $discount = $this->discountFor($coupon, $course);
        $subtotal = $course->price - $discount;
        $gst      = (int) round($subtotal * self::GST_RATE);

        $order = Order::create([
            'user_id'     => $user->id,
            'course_id'   => $course->id,
            'amount'      => $subtotal + $gst,
            'gst'         => $gst,
            'coupon_code' => $coupon,
            'status'      => 'created',
        ]);

        $rzp = app('razorpay')->order->create([
            'receipt'  => "NPA-{$order->id}",
            'amount'   => $order->amount,          // paise
            'currency' => 'INR',
        ]);

        $order->update(['razorpay_order_id' => $rzp['id']]);

        return $order;
    }

    /**
     * Verifies the payment signature and, only then, grants access.
     *
     * Wrapped in a transaction so a failure between "mark paid" and "write
     * enrolment" cannot leave a student who has paid without a course.
     */
    public function verifyAndFulfil(array $payload): Order
    {
        $order = Order::where('razorpay_order_id', $payload['razorpay_order_id'])->firstOrFail();

        $expected = hash_hmac(
            'sha256',
            $payload['razorpay_order_id'].'|'.$payload['razorpay_payment_id'],
            config('services.razorpay.secret')
        );

        if (! hash_equals($expected, $payload['razorpay_signature'])) {
            $order->update(['status' => 'failed']);
            throw new RuntimeException('Payment signature verification failed.');
        }

        return DB::transaction(function () use ($order, $payload) {
            $order->update([
                'status'              => 'paid',
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
            ]);

            $this->enrolments->grant($order->user, $order->course, $order);

            return $order;
        });
    }

    private function discountFor(?string $coupon, Course $course): int
    {
        return match (strtoupper((string) $coupon)) {
            'NAVPATH10' => (int) round($course->price * 0.10),
            default     => 0,
        };
    }
}
