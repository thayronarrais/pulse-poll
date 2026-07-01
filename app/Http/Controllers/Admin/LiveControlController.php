<?php

namespace App\Http\Controllers\Admin;

use App\Events\LiveSessionStateChanged;
use App\Events\QuestionClosed;
use App\Events\QuestionOpened;
use App\Events\QuestionTextUpdated;
use App\Http\Controllers\Controller;
use App\Models\LiveParticipant;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class LiveControlController extends Controller
{
    public function panel(LiveSession $liveSession): View
    {
        $this->authorizeSession($liveSession);

        $liveSession->load('questions', 'participants');

        return view('admin.live.control', compact('liveSession'));
    }

    public function responses(LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($liveSession);

        return response()->json($liveSession->participantResponses());
    }

    public function toggleMaster(LiveSession $liveSession, LiveParticipant $participant): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $participant->update(['is_master' => ! $participant->is_master]);

        return response()->json(['id' => $participant->id, 'is_master' => $participant->is_master]);
    }

    public function start(LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $liveSession->update(['status' => LiveSession::STATUS_LIVE]);

        rescue(fn () => broadcast(new LiveSessionStateChanged($liveSession)), report: false);

        return response()->json(['status' => $liveSession->status]);
    }

    public function openQuestion(LiveSession $liveSession, LiveQuestion $question): JsonResponse
    {
        $this->authorizeSession($liveSession);

        if (! $liveSession->isLive()) {
            $liveSession->update(['status' => LiveSession::STATUS_LIVE]);
        }

        // Close any currently open question first.
        $liveSession->questions()
            ->where('status', LiveQuestion::STATUS_OPEN)
            ->update(['status' => LiveQuestion::STATUS_CLOSED]);

        $question->update(['status' => LiveQuestion::STATUS_OPEN]);
        $liveSession->update(['current_question_id' => $question->id]);

        rescue(fn () => broadcast(new QuestionOpened($question)), report: false);

        return response()->json(['question_id' => $question->id, 'status' => $question->status]);
    }

    /**
     * Create a brand-new question on the fly and open it immediately.
     * The presenter only picks the choice type; no title is needed.
     */
    public function askQuestion(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $validator = Validator::make($request->all(), [
            'choice_type' => 'required|in:ab,abcd,simnao',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('live.invalid_choice_type'), 'errors' => $validator->errors()], 422);
        }

        if (! $liveSession->isLive()) {
            $liveSession->update(['status' => LiveSession::STATUS_LIVE]);
        }

        // Close any currently open question first.
        $liveSession->questions()
            ->where('status', LiveQuestion::STATUS_OPEN)
            ->update(['status' => LiveQuestion::STATUS_CLOSED]);

        $order = ($liveSession->questions()->max('order') ?? -1) + 1;

        $question = $liveSession->questions()->create([
            'choice_type' => $validator->validated()['choice_type'],
            'status' => LiveQuestion::STATUS_OPEN,
            'order' => $order,
        ]);

        $liveSession->update(['current_question_id' => $question->id]);

        rescue(fn () => broadcast(new QuestionOpened($question)), report: false);

        return response()->json($this->questionPayload($liveSession, $question));
    }

    public function updateQuestion(Request $request, LiveSession $liveSession, LiveQuestion $question): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $validator = Validator::make($request->all(), [
            'admin_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('live.invalid_text'), 'errors' => $validator->errors()], 422);
        }

        $question->update(['admin_text' => $validator->validated()['admin_text'] ?? null]);

        rescue(fn () => broadcast(new QuestionTextUpdated($question)), report: false);

        return response()->json(['id' => $question->id, 'admin_text' => $question->admin_text]);
    }

    public function closeQuestion(LiveSession $liveSession, LiveQuestion $question): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $question->update(['status' => LiveQuestion::STATUS_CLOSED]);

        rescue(fn () => broadcast(new QuestionClosed($question)), report: false);

        return response()->json([
            'question_id' => $question->id,
            'tally' => $question->tally(),
        ]);
    }

    public function finish(LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($liveSession);

        $liveSession->questions()
            ->where('status', LiveQuestion::STATUS_OPEN)
            ->update(['status' => LiveQuestion::STATUS_CLOSED]);

        $liveSession->update([
            'status' => LiveSession::STATUS_FINISHED,
            'current_question_id' => null,
        ]);

        rescue(fn () => broadcast(new LiveSessionStateChanged($liveSession)), report: false);

        return response()->json(['status' => $liveSession->status]);
    }

    private function authorizeSession(LiveSession $liveSession): void
    {
        abort_unless($liveSession->user_id === auth()->id(), 403);
    }

    /**
     * Shape a question for the control panel (mirrors the Blade $config payload).
     *
     * @return array<string, mixed>
     */
    private function questionPayload(LiveSession $liveSession, LiveQuestion $question): array
    {
        return [
            'id' => $question->id,
            'admin_text' => $question->admin_text,
            'choice_type' => $question->choice_type,
            'choices' => $question->choices(),
            'status' => $question->status,
            'order' => $question->order,
            'tally' => $question->tally(),
            'openUrl' => route('admin.live-sessions.questions.open', [$liveSession, $question]),
            'closeUrl' => route('admin.live-sessions.questions.close', [$liveSession, $question]),
            'updateUrl' => route('admin.live-sessions.questions.update', [$liveSession, $question]),
        ];
    }
}
