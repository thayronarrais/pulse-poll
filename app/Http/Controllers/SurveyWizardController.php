<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Survey;

class SurveyWizardController extends Controller
{
    public function show(Survey $survey)
    {
        $survey->load(['questions' => function ($q) {
            $q->orderBy('order', 'asc');
        }, 'questions.options']);

        $highlightedResponses = $survey->responses()
                                       ->where('is_highlighted', true)
                                       ->whereNotNull('name')
                                       ->with('answers')
                                       ->get();

        return view('surveys.wizard', compact('survey', 'highlightedResponses'));
    }

    public function submit(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'answers' => 'required|array',
        ]);

        $response = $survey->responses()->create([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        foreach ($validated['answers'] as $questionId => $options) {
            if (!is_array($options)) {
                $options = [$options]; // Normalize to array
            }
            foreach ($options as $optionId) {
                if ($optionId) {
                    $response->answers()->create([
                        'question_id' => $questionId,
                        'option_id' => $optionId,
                    ]);
                }
            }
        }

        return view('surveys.success');
    }
}
