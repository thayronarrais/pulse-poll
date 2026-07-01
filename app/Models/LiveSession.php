<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSession extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = ['user_id', 'title', 'slug', 'status', 'current_question_id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LiveQuestion::class)->orderBy('order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LiveParticipant::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(LiveQuestion::class, 'current_question_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Choice picked by each identified participant for each question, keyed by
     * participant id then question id. Participants without a vote for a given
     * question simply have no entry for it.
     *
     * @return array<int, array<int, string>>
     */
    public function participantResponses(): array
    {
        $tokenToId = $this->participants()->pluck('id', 'device_token');

        if ($tokenToId->isEmpty()) {
            return [];
        }

        $questionIds = $this->questions()->pluck('id');

        $votes = LiveVote::query()
            ->whereIn('live_question_id', $questionIds)
            ->whereIn('device_token', $tokenToId->keys())
            ->get(['live_question_id', 'device_token', 'choice']);

        $map = [];
        foreach ($votes as $vote) {
            $participantId = $tokenToId[$vote->device_token];
            $map[$participantId][$vote->live_question_id] = $vote->choice;
        }

        return $map;
    }
}
