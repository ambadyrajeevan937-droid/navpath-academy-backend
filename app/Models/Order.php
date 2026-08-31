<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = ['user_id', 'course_id', 'amount', 'gst', 'coupon_code',
                           'razorpay_order_id', 'razorpay_payment_id', 'status'];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
