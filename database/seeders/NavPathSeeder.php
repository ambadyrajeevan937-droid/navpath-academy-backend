<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the same catalogue the web prototype renders, so the database and the
 * prototype tell the same story. Prices are in paise.
 */
class NavPathSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::updateOrCreate(
            ['email' => 'arjun.menon@example.com'],
            [
                'name' => 'Arjun Menon', 'password' => Hash::make('password'),
                'phone' => '+91 98470 12345', 'target_exam' => 'IMU CET 2027',
                'school' => 'St. Berchmans HSS, Changanassery', 'stream' => '+2 Science (PCM)',
                'learnyst_user_id' => 'ln_user_1001',
            ]
        );

        $courses = [
            ['imu-cet-complete',    'IMU CET 2027 Complete Programme', 'imu-cet',    1499900, 2299900, 'Full-syllabus flagship batch for first-time IMU CET aspirants.'],
            ['imu-cet-repeaters',   'IMU CET Repeaters Intensive',     'repeaters',   999900, 1599900, 'Diagnostic-led revision for a second attempt.'],
            ['dns-sponsorship',     'DNS & Company Sponsorship Track', 'dns',        1199900, 1799900, 'Entrance, written rounds, interview panels and medicals.'],
            ['foundation-plus-two', '+1 / +2 Maritime Foundation',     'foundation',  799900, 1199900, 'Builds the Class 11-12 PCM base alongside school.'],
            ['spoken-english',      'Spoken English & Interview Confidence', 'interview', 499900, 799900, 'Fluency and presence for sponsorship panels.'],
            ['crash-course-60',     'IMU CET 60-Day Crash Course',     'imu-cet',     599900,  999900, 'Final-stretch revision sprint.'],
        ];

        foreach ($courses as [$slug, $title, $cat, $price, $mrp, $desc]) {
            $course = Course::updateOrCreate(
                ['slug' => $slug],
                [
                    'learnyst_course_id' => 'ln_'.str_replace('-', '_', $slug),
                    'title' => $title, 'category' => $cat, 'description' => $desc,
                    'price' => $price, 'mrp' => $mrp, 'validity_days' => 365,
                    'is_published' => true, 'synced_at' => now(),
                ]
            );

            if ($slug === 'imu-cet-complete') {
                $this->seedFlagship($course, $student);
            }
        }
    }

    private function seedFlagship(Course $course, User $student): void
    {
        $lessons = [
            ['m1', 'How IMU CET is actually scored', 862, true],
            ['m1', 'Building your 6-month study plan', 1120, true],
            ['m1', 'Negative marking arithmetic', 665, false],
            ['m2', 'Quadratic equations - CET patterns', 1574, false],
            ['m2', 'Trigonometric identities, fast', 1367, false],
            ['m3', "Newton's laws in exam questions", 1691, false],
            ['m3', 'Work, energy and power', 1503, false],
            ['m4', 'Atomic structure essentials', 1176, false],
            ['m5', 'Reading comprehension strategy', 1040, false],
            ['m6', 'Maritime GK you must know', 1304, false],
        ];
        foreach ($lessons as $i => [$module, $title, $dur, $free]) {
            Lesson::updateOrCreate(
                ['course_id' => $course->id, 'title' => $title],
                ['module_id' => $module, 'duration_sec' => $dur, 'is_free' => $free,
                 'position' => $i + 1, 'learnyst_asset_id' => 'ln_asset_'.($i + 1)]
            );
        }

        $materials = [
            ['m1', 'IMU CET 2027 - Exam Blueprint & Marking Scheme', 12, true],
            ['m2', 'Mathematics - Complete Formula Sheet', 28, true],
            ['m3', 'Physics - Mechanics Notes', 34, true],
            ['m6', 'Maritime GK & Current Affairs - August 2026', 16, true],
            ['m7', 'IMU CET Previous Year Papers (2019-2026)', 148, false],
        ];
        foreach ($materials as [$module, $title, $pages, $dl]) {
            Material::updateOrCreate(
                ['course_id' => $course->id, 'title' => $title],
                ['module_id' => $module, 'pages' => $pages, 'is_downloadable' => $dl,
                 'storage_key' => 'materials/'.$course->slug.'/'.md5($title).'.pdf']
            );
        }

        $tests = [
            ['IMU CET Full Mock 01',        'full_mock',  200, 180, 2],
            ['Sectional - Mathematics 01',  'sectional',   25,  30, 3],
            ['Sectional - Physics 01',      'sectional',   25,  30, 3],
            ['Weekly Homework - Chemistry', 'homework',    15,  20, 1],
        ];
        foreach ($tests as $i => [$title, $kind, $q, $mins, $attempts]) {
            Test::updateOrCreate(
                ['course_id' => $course->id, 'title' => $title],
                ['kind' => $kind, 'question_count' => $q, 'duration_min' => $mins,
                 'max_attempts' => $attempts, 'learnyst_test_id' => 'ln_test_'.($i + 1),
                 'opens_at' => now()->subDays(7), 'closes_at' => now()->addDays(30)]
            );
        }

        Enrolment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'active', 'starts_at' => now(),
             'expires_at' => now()->addDays($course->validity_days),
             'learnyst_enrolment_id' => 'ln_enr_5001']
        );
    }
}
