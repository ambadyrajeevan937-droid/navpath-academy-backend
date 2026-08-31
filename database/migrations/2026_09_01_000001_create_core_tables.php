<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel ships its own users table; we extend it rather than replace it.
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->after('email')->index();
            $t->string('target_exam')->nullable();
            $t->string('school')->nullable();
            $t->string('stream')->nullable();
            // Maps our canonical user onto Learnyst's. Ours is the source of truth.
            $t->string('learnyst_user_id')->nullable()->unique();
        });

        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('learnyst_course_id')->unique();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('category')->index();
            $t->text('description')->nullable();
            $t->unsignedInteger('price');           // paise — never float money
            $t->unsignedInteger('mrp');
            $t->unsignedSmallInteger('validity_days')->default(365);
            $t->boolean('is_published')->default(false)->index();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount');          // computed server-side only
            $t->unsignedInteger('gst');
            $t->string('coupon_code')->nullable();
            $t->string('razorpay_order_id')->nullable()->index();
            $t->string('razorpay_payment_id')->nullable();
            $t->enum('status', ['created', 'paid', 'failed', 'refunded'])->default('created');
            $t->timestamps();
        });

        Schema::create('enrolments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('status', ['active', 'expired', 'refunded', 'suspended'])->default('active');
            $t->timestamp('starts_at');
            $t->timestamp('expires_at')->nullable();
            $t->string('learnyst_enrolment_id')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'course_id']);
            // The entitlement check runs on essentially every authenticated request.
            $t->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('module_id')->index();
            $t->string('title');
            $t->unsignedInteger('duration_sec')->default(0);
            $t->string('learnyst_asset_id')->nullable();
            $t->boolean('is_free')->default(false);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('watched_sec')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'lesson_id']);
        });

        Schema::create('materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('module_id')->nullable();
            $t->string('title');
            $t->string('storage_key');               // R2 object key, never a public URL
            $t->unsignedSmallInteger('pages')->default(0);
            $t->boolean('is_downloadable')->default(false);
            $t->timestamps();
        });

        Schema::create('tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('learnyst_test_id')->nullable();
            $t->string('title');
            $t->string('kind')->default('sectional');
            $t->unsignedSmallInteger('question_count');
            $t->unsignedSmallInteger('duration_min');
            $t->unsignedTinyInteger('max_attempts')->default(1);
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closes_at')->nullable();
            $t->timestamps();
        });

        Schema::create('attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('submitted_at')->nullable();
            // decimal, not float: -0.25 marking has to be exact.
            $t->decimal('score', 6, 2)->nullable();
            $t->unsignedSmallInteger('max_score')->nullable();
            $t->unsignedSmallInteger('correct')->default(0);
            $t->unsignedSmallInteger('wrong')->default(0);
            $t->unsignedSmallInteger('skipped')->default(0);
            $t->json('section_breakdown')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'test_id']);
        });

        Schema::create('answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $t->string('question_id');
            $t->unsignedTinyInteger('selected_option')->nullable();
            $t->boolean('is_correct')->nullable();
            $t->timestamps();

            $t->unique(['attempt_id', 'question_id']);
        });

        // Device binding: the cheapest effective control against credential sharing.
        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('device_id');
            $t->string('platform', 16);
            $t->timestamp('last_seen_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        foreach (['devices','answers','attempts','tests','materials','progress',
                  'lessons','enrolments','orders','courses'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['phone','target_exam','school','stream','learnyst_user_id']);
        });
    }
};
