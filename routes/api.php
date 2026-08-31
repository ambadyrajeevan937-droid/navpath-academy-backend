<?php

use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Public — the catalogue is the SEO surface and the top of the funnel.
*/
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

/*
| Authenticated (Sanctum).
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $r) => $r->user()->only([
        'id', 'name', 'email', 'phone', 'target_exam', 'school', 'stream',
    ]));

    Route::get('/me/enrolments', fn (Request $r) => $r->user()
        ->enrolments()->active()->with('course:id,slug,title')->get());

    Route::post('/checkout/{course}', [CheckoutController::class, 'create']);
    Route::post('/checkout/verify',   [CheckoutController::class, 'verify']);

    Route::get('/lessons/{lesson}',           [LessonController::class, 'show']);
    Route::post('/lessons/{lesson}/progress', [LessonController::class, 'progress']);

    Route::post('/tests/{test}/attempts',     [AttemptController::class, 'start']);
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit']);
});
