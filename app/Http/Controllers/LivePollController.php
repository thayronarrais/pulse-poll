<?php

namespace App\Http\Controllers;

use App\Events\MasterVoted;
use App\Events\ParticipantIdentified;
use App\Events\ResultsUpdated;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\LiveVote;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LivePollController extends Controller
{
    private const COOKIE_NAME = 'live_device_token';

    private const NAME_COOKIE = 'live_voter_name';

    public function show(Request $request, LiveSession $liveSession): Response
    {
        $token = $request->cookie(self::COOKIE_NAME) ?: Str::random(40);

        return response()
            ->view('live.vote', [
                'liveSession' => $liveSession,
                'state' => $this->buildState($liveSession, $token),
                'voterName' => $request->cookie(self::NAME_COOKIE) ?: '',
            ])
            ->withCookie(Cookie::make(self::COOKIE_NAME, $token, 60 * 24 * 30));
    }

    public function state(Request $request, LiveSession $liveSession): JsonResponse
    {
        $token = $request->cookie(self::COOKIE_NAME) ?: '';

        return response()->json($this->buildState($liveSession, $token));
    }

    /**
     * Register (or update) the participant's optional identification once, on entry.
     */
    public function identify(Request $request, LiveSession $liveSession): JsonResponse
    {
        $token = $request->cookie(self::COOKIE_NAME) ?: Str::random(40);

        // Only the name is required to identify; the photo is best-effort so a
        // problematic file never blocks someone from joining.
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('live.identify_name_required'), 'errors' => $validator->errors()], 422);
        }

        $name = trim((string) $request->input('name'));

        $participant = $liveSession->participants()->firstOrNew(['device_token' => $token]);
        $participant->name = $name;

        $photo = $request->file('photo');
        if ($photo && $photo->isValid() && str_starts_with((string) $photo->getMimeType(), 'image/') && $photo->getSize() <= 8 * 1024 * 1024) {
            if ($participant->photo_path) {
                Storage::disk('public')->delete($participant->photo_path);
            }
            $participant->photo_path = $photo->store('live-photos', 'public');
        }

        $participant->save();

        rescue(fn () => broadcast(new ParticipantIdentified($participant)), report: false);

        return response()->json(['ok' => true, 'name' => $name, 'photo_url' => $participant->photo_url])
            ->withCookie(Cookie::make(self::COOKIE_NAME, $token, 60 * 24 * 30))
            ->withCookie(Cookie::make(self::NAME_COOKIE, $name, 60 * 24 * 30));
    }

    public function vote(Request $request, LiveSession $liveSession): JsonResponse
    {
        $question = $liveSession->currentQuestion;

        if (! $liveSession->isLive() || ! $question || ! $question->isOpen()) {
            return response()->json(['message' => __('live.voting_closed')], 409);
        }

        // Validate manually so the error renders as JSON regardless of the
        // app's `api/*`-only JSON exception rendering (see bootstrap/app.php).
        $validator = Validator::make($request->all(), [
            'choice' => ['required', 'string', 'in:'.implode(',', $question->choices())],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('live.invalid_choice'), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $choice = $validated['choice'];
        $voterName = isset($validated['name']) ? trim($validated['name']) : null;
        $voterName = $voterName !== '' ? $voterName : null;
        $token = $request->cookie(self::COOKIE_NAME) ?: Str::random(40);

        try {
            LiveVote::create([
                'live_question_id' => $question->id,
                'device_token' => $token,
                'voter_name' => $voterName,
                'choice' => $choice,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json(['message' => __('live.already_voted')], 409)
                    ->withCookie(Cookie::make(self::COOKIE_NAME, $token, 60 * 24 * 30));
            }
            throw $e;
        }

        // Throttle: at most one results broadcast per second per question.
        // Wrapped so an unavailable broadcaster never fails the vote itself.
        if (Cache::add('live_throttle:'.$question->id, 1, 1)) {
            rescue(fn () => broadcast(new ResultsUpdated($question)), report: false);
        }

        // If this device belongs to a master, push their pick to everyone live.
        $participant = $liveSession->participants()->where('device_token', $token)->first();
        if ($participant && $participant->is_master) {
            rescue(fn () => broadcast(new MasterVoted($question, $participant, $choice)), report: false);
        }

        $response = response()->json(['ok' => true])
            ->withCookie(Cookie::make(self::COOKIE_NAME, $token, 60 * 24 * 30));

        if ($voterName !== null) {
            $response->withCookie(Cookie::make(self::NAME_COOKIE, $voterName, 60 * 24 * 30));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildState(LiveSession $liveSession, string $token): array
    {
        $question = $liveSession->currentQuestion;

        $hasVoted = $question && $token
            ? $question->votes()->where('device_token', $token)->exists()
            : false;

        return [
            'session_status' => $liveSession->status,
            'question' => $question ? [
                'id' => $question->id,
                'choice_type' => $question->choice_type,
                'choices' => $question->choices(),
                'status' => $question->status,
            ] : null,
            'has_voted' => $hasVoted,
            'tally' => $question && ($hasVoted || $question->status === LiveQuestion::STATUS_CLOSED)
                ? $question->tally()
                : null,
            'master_picks' => $this->masterPicks($liveSession, $question),
        ];
    }

    /**
     * Choices picked by the session's masters for the given question, keyed by
     * choice. Used to render suggestion badges on the voter screen.
     *
     * @return array<string, array<int, array{name: string, photo_url: ?string}>>
     */
    private function masterPicks(LiveSession $liveSession, ?LiveQuestion $question): array
    {
        if (! $question) {
            return [];
        }

        $masters = $liveSession->participants()->where('is_master', true)->get()->keyBy('device_token');

        if ($masters->isEmpty()) {
            return [];
        }

        $picks = [];

        foreach ($question->votes()->whereIn('device_token', $masters->keys())->get() as $vote) {
            $master = $masters->get($vote->device_token);
            $picks[$vote->choice][] = [
                'name' => $master->name,
                'photo_url' => $master->photo_url,
            ];
        }

        return $picks;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
