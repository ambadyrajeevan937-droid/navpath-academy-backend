<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Test;
use App\Services\LearnystService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttemptController extends Controller
{
    public function __construct(private readonly LearnystService $learnyst) {}

    /** Starts an attempt. The server owns the clock, so the client cannot extend it. */
    public function start(Request $request, Test $test): JsonResponse
    {
        abort_unless($test->isOpen(), 403, 'This test is not currently open.');

        $used = Attempt::where('user_id', $request->user()->id)->where('test_id', $test->id)->count();
        abort_if($used >= $test->max_attempts, 403, 'No attempts remaining.');

        $attempt = Attempt::create([
            'user_id'    => $request->user()->id,
            'test_id'    => $test->id,
            'started_at' => now(),
            'max_score'  => $test->question_count,
        ]);

        $questions = $this->learnyst->questions($test->learnyst_test_id);

        return response()->json([
            'attempt_id' => $attempt->id,
            // Correct answers are stripped before the paper leaves the server.
            'questions'  => $questions->map(fn ($q) => collect($q)->except('answer', 'explanation')),
            'expires_at' => $attempt->started_at->addMinutes($test->duration_min),
        ]);
    }

    /**
     * Submits and scores. Scoring happens here, from the authoritative answer
     * key — the client sends selections, never a score.
     */
    public function submit(Request $request, Attempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_if($attempt->submitted_at !== null, 409, 'Attempt already submitted.');

        $data = $request->validate([
            'answers'                   => ['required', 'array'],
            'answers.*.question_id'     => ['required', 'string'],
            'answers.*.selected_option' => ['nullable', 'integer', 'min:0', 'max:3'],
        ]);

        $test = $attempt->test;

        // Server-side clock check: a late submission is accepted but truncated
        // at the deadline, so a tampered device clock gains nothing.
        $deadline = $attempt->started_at->addMinutes($test->duration_min);

        $key = $this->learnyst->questions($test->learnyst_test_id)->keyBy('id');

        return DB::transaction(function () use ($attempt, $data, $key, $test, $deadline) {
            $correct = $wrong = 0;
            $sections = [];

            foreach ($data['answers'] as $row) {
                $q = $key->get($row['question_id']);
                if (! $q) continue;

                $section = $q['section'] ?? 'General';
                $sections[$section] ??= ['correct' => 0, 'wrong' => 0, 'skipped' => 0, 'total' => 0];
                $sections[$section]['total']++;

                $selected = $row['selected_option'] ?? null;

                if ($selected === null) {
                    $sections[$section]['skipped']++;
                    $isCorrect = null;
                } elseif ($selected === $q['answer']) {
                    $correct++; $sections[$section]['correct']++; $isCorrect = true;
                } else {
                    $wrong++; $sections[$section]['wrong']++; $isCorrect = false;
                }

                Answer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $row['question_id']],
                    ['selected_option' => $selected, 'is_correct' => $isCorrect]
                );
            }

            $attempt->fill([
                'correct'           => $correct,
                'wrong'             => $wrong,
                'skipped'           => $test->question_count - $correct - $wrong,
                'section_breakdown' => $sections,
                'submitted_at'      => min(now(), $deadline),
            ]);
            $attempt->score = $attempt->computeScore();
            $attempt->save();

            $this->learnyst->submitAttempt($test->learnyst_test_id, [
                'user_ref' => $attempt->user->learnyst_user_id,
                'score'    => $attempt->score,
            ]);

            return response()->json([
                'score'     => $attempt->score,
                'max_score' => $attempt->max_score,
                'correct'   => $correct,
                'wrong'     => $wrong,
                'skipped'   => $attempt->skipped,
                'accuracy'  => $attempt->accuracy(),
                'sections'  => $sections,
            ]);
        });
    }
}
