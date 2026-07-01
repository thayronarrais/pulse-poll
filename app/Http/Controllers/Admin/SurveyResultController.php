<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;

class SurveyResultController extends Controller
{
    public function index(Request $request, Survey $survey)
    {
        $survey->load(['questions' => function ($q) {
            $q->orderBy('order', 'asc');
        }, 'questions.options']);

        $query = $survey->responses()->with('answers');

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('name')) {
            $query->where('name', $request->name);
        }

        $respondentNames = $survey->responses()
            ->whereNotNull('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name');

        $responses = $query->latest()->get();
        $totalResponses = $responses->count();
        $identifiedResponses = $responses->whereNotNull('name')->count();

        // Calculate statistics for charts
        $stats = [];
        foreach ($survey->questions as $question) {
            $stats[$question->id] = [
                'question_text' => $question->text,
                'labels' => [],
                'data' => [],
                'backgroundColor' => [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                ],
            ];
            foreach ($question->options as $option) {
                $stats[$question->id]['labels'][] = $option->text;
                $count = 0;
                foreach ($responses as $response) {
                    if ($response->answers->where('option_id', $option->id)->count() > 0) {
                        $count++;
                    }
                }
                $stats[$question->id]['data'][] = $count;
            }
        }

        return view('admin.surveys.results', compact('survey', 'responses', 'totalResponses', 'identifiedResponses', 'stats', 'respondentNames'));
    }

    public function toggleHighlight(Survey $survey, $responseId)
    {
        try {
            $response = SurveyResponse::findOrFail($responseId);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Model not found: '.$responseId]);
        }

        if ($response->survey_id != $survey->id) {
            return response()->json(['error' => 'Survey mismatch', 'r_s_id' => $response->survey_id, 's_id' => $survey->id]);
        }

        $response->is_highlighted = ! $response->is_highlighted;
        $response->save();

        return response()->json([
            'success' => true,
            'is_highlighted' => $response->is_highlighted,
        ]);
    }
}
