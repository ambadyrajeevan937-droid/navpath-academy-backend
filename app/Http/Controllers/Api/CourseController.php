<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The catalogue is deliberately public — it is the SEO surface and the top of
 * the acquisition funnel. Gating it would cost exactly the searches the ASO
 * strategy is built to win.
 */
class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->where('is_published', true)
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->withCount(['lessons', 'materials', 'tests'])
            ->orderBy('id')
            ->get()
            ->map(fn (Course $c) => $this->present($c));

        return response()->json(['data' => $courses]);
    }

    public function show(Course $course): JsonResponse
    {
        abort_unless($course->is_published, 404);

        $course->loadCount(['lessons', 'materials', 'tests']);

        return response()->json(['data' => $this->present($course) + [
            'lessons' => $course->lessons()->get(['id', 'module_id', 'title', 'duration_sec', 'is_free']),
            'tests'   => $course->tests()->get(['id', 'title', 'kind', 'question_count', 'duration_min']),
        ]]);
    }

    /** Money is stored in paise; the API exposes rupees so clients never divide. */
    private function present(Course $c): array
    {
        return [
            'id'          => $c->id,
            'slug'        => $c->slug,
            'title'       => $c->title,
            'category'    => $c->category,
            'description' => $c->description,
            'price'       => $c->price / 100,
            'mrp'         => $c->mrp / 100,
            'counts'      => [
                'lessons'   => $c->lessons_count,
                'materials' => $c->materials_count,
                'tests'     => $c->tests_count,
            ],
        ];
    }
}
