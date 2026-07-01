<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Survey;

class SurveyController extends Controller
{
    public function index()
    {
        $surveys = Survey::withCount('questions')->latest()->get();
        return view('admin.surveys.index', compact('surveys'));
    }

    public function create()
    {
        return view('admin.surveys.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'theme_color' => 'nullable|string|max:20',
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string|max:255',
            'questions.*.type' => 'required|in:single,multiple,limited',
            'questions.*.limit' => 'nullable|integer|min:1',
            'questions.*.image' => 'nullable|image|max:5120',
            'questions.*.giphy_url' => 'nullable|url',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.text' => 'required|string|max:255',
        ]);

        $survey = Survey::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => Str::slug($validated['title']) . '-' . uniqid(),
            'theme_color' => $validated['theme_color'] ?? '#4f46e5',
        ]);

        $order = 0;
        foreach ($validated['questions'] as $index => $qData) {
            $imagePath = null;
            if (!empty($qData['giphy_url'])) {
                $imagePath = $qData['giphy_url'];
            } elseif ($request->hasFile("questions.{$index}.image")) {
                $imagePath = $request->file("questions.{$index}.image")->store('question_images', 'public');
            }

            $question = $survey->questions()->create([
                'text' => $qData['text'],
                'type' => $qData['type'],
                'limit' => $qData['type'] === 'limited' ? ($qData['limit'] ?? 1) : null,
                'order' => $order++,
                'image_path' => $imagePath,
            ]);

            foreach ($qData['options'] as $oData) {
                $question->options()->create([
                    'text' => $oData['text']
                ]);
            }
        }

        return redirect()->route('admin.surveys.index')->with('success', __('admin.surveys.created_flash'));
    }

    public function show(Survey $survey)
    {
        $survey->load(['questions' => function ($q) {
            $q->orderBy('order', 'asc');
        }, 'questions.options']);
        return view('admin.surveys.show', compact('survey'));
    }

    public function edit(Survey $survey)
    {
        $survey->load(['questions' => function ($q) {
            $q->orderBy('order', 'asc');
        }, 'questions.options']);
        return view('admin.surveys.edit', compact('survey'));
    }

    public function update(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'theme_color' => 'nullable|string|max:20',
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'nullable|exists:questions,id',
            'questions.*.text' => 'required|string|max:255',
            'questions.*.type' => 'required|in:single,multiple,limited',
            'questions.*.limit' => 'nullable|integer|min:1',
            'questions.*.image' => 'nullable|image|max:5120',
            'questions.*.giphy_url' => 'nullable|url',
            'questions.*.remove_image' => 'nullable',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.id' => 'nullable|exists:options,id',
            'questions.*.options.*.text' => 'required|string|max:255',
        ]);

        $survey->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'theme_color' => $validated['theme_color'] ?? '#4f46e5',
        ]);

        $keptQuestionIds = [];
        $order = 0;

        foreach ($validated['questions'] as $index => $qData) {
            $imagePath = null;
            
            if (!empty($qData['id'])) {
                // Update existing
                $question = $survey->questions()->find($qData['id']);
                $imagePath = $question->image_path;

                if (!empty($qData['remove_image']) || !empty($qData['giphy_url'])) {
                    if ($imagePath && !Str::startsWith($imagePath, 'http')) {
                        Storage::disk('public')->delete($imagePath);
                    }
                    $imagePath = null;
                }

                if (!empty($qData['giphy_url'])) {
                    $imagePath = $qData['giphy_url'];
                } elseif ($request->hasFile("questions.{$index}.image")) {
                    if ($imagePath && !Str::startsWith($imagePath, 'http')) {
                        Storage::disk('public')->delete($imagePath);
                    }
                    $imagePath = $request->file("questions.{$index}.image")->store('question_images', 'public');
                }

                $question->update([
                    'text' => $qData['text'],
                    'type' => $qData['type'],
                    'limit' => $qData['type'] === 'limited' ? ($qData['limit'] ?? 1) : null,
                    'order' => $order++,
                    'image_path' => $imagePath,
                ]);
            } else {
                // Create new
                if (!empty($qData['giphy_url'])) {
                    $imagePath = $qData['giphy_url'];
                } elseif ($request->hasFile("questions.{$index}.image")) {
                    $imagePath = $request->file("questions.{$index}.image")->store('question_images', 'public');
                }

                $question = $survey->questions()->create([
                    'text' => $qData['text'],
                    'type' => $qData['type'],
                    'limit' => $qData['type'] === 'limited' ? ($qData['limit'] ?? 1) : null,
                    'order' => $order++,
                    'image_path' => $imagePath,
                ]);
            }
            $keptQuestionIds[] = $question->id;

            $keptOptionIds = [];
            foreach ($qData['options'] as $oData) {
                if (!empty($oData['id'])) {
                    $option = $question->options()->find($oData['id']);
                    $option->update(['text' => $oData['text']]);
                } else {
                    $option = $question->options()->create(['text' => $oData['text']]);
                }
                $keptOptionIds[] = $option->id;
            }
            $question->options()->whereNotIn('id', $keptOptionIds)->delete();
        }

        // Delete removed questions and their images
        $questionsToDelete = $survey->questions()->whereNotIn('id', $keptQuestionIds)->get();
        foreach ($questionsToDelete as $q) {
            if ($q->image_path && !Str::startsWith($q->image_path, 'http')) {
                Storage::disk('public')->delete($q->image_path);
            }
            $q->delete();
        }

        return redirect()->route('admin.surveys.index')->with('success', __('admin.surveys.updated_flash'));
    }

    public function destroy(Survey $survey)
    {
        foreach ($survey->questions as $q) {
            if ($q->image_path && !Str::startsWith($q->image_path, 'http')) {
                Storage::disk('public')->delete($q->image_path);
            }
        }
        $survey->delete();
        return redirect()->route('admin.surveys.index')->with('success', __('admin.surveys.deleted_flash'));
    }
}
