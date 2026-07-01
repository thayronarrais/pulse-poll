<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LiveSessionController extends Controller
{
    public function index(): View
    {
        $sessions = LiveSession::where('user_id', auth()->id())
            ->withCount('questions')
            ->latest()
            ->get();

        return view('admin.live.index', compact('sessions'));
    }

    public function create(): View
    {
        return view('admin.live.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:255']);

        $liveSession = LiveSession::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.uniqid(),
            'status' => LiveSession::STATUS_DRAFT,
        ]);

        return redirect()
            ->route('admin.live-sessions.control', $liveSession)
            ->with('success', __('admin.live.created_flash'));
    }

    public function edit(LiveSession $liveSession): View
    {
        abort_unless($liveSession->user_id === auth()->id(), 403);
        abort_unless($liveSession->isDraft(), 403, __('admin.live.only_draft_editable'));

        $liveSession->load('questions');

        return view('admin.live.edit', compact('liveSession'));
    }

    public function update(Request $request, LiveSession $liveSession): RedirectResponse
    {
        abort_unless($liveSession->user_id === auth()->id(), 403);
        abort_unless($liveSession->isDraft(), 403, __('admin.live.only_draft_editable'));

        $validated = $request->validate(['title' => 'required|string|max:255']);

        $liveSession->update(['title' => $validated['title']]);

        return redirect()
            ->route('admin.live-sessions.index')
            ->with('success', __('admin.live.updated_flash'));
    }

    public function destroy(LiveSession $liveSession): RedirectResponse
    {
        abort_unless($liveSession->user_id === auth()->id(), 403);

        $liveSession->delete();

        return redirect()
            ->route('admin.live-sessions.index')
            ->with('success', __('admin.live.deleted_flash'));
    }
}
