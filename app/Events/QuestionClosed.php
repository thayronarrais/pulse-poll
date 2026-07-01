<?php

namespace App\Events;

use App\Models\LiveQuestion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveQuestion $question) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('live-session.'.$this->question->session->slug),
        ];
    }

    public function broadcastAs(): string
    {
        return 'question.closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $tally = $this->question->tally();

        return [
            'question_id' => $this->question->id,
            'tally' => $tally,
            'total' => array_sum($tally),
            'status' => LiveQuestion::STATUS_CLOSED,
        ];
    }
}
