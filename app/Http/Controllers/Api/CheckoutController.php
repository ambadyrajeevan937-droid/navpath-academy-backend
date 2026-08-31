<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Note what the client may send: a course and a coupon. Never an amount. */
    public function create(Request $request, Course $course): JsonResponse
    {
        $request->validate(['coupon' => ['nullable', 'string', 'max:32']]);

        $order = $this->payments->createOrder($request->user(), $course, $request->input('coupon'));

        return response()->json([
            'order_id' => $order->razorpay_order_id,
            'amount'   => $order->amount,
            'currency' => 'INR',
            'key'      => config('services.razorpay.key'),   // publishable key only
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        $order = $this->payments->verifyAndFulfil($data);

        return response()->json([
            'status'   => 'enrolled',
            'course'   => $order->course->slug,
            'order_id' => $order->id,
        ]);
    }
}
