<?php

namespace App\Events;

use App\Models\LiveQuestion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionOpened implements ShouldBroadcastNow
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
        return 'question.opened';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'choice_type' => $this->question->choice_type,
            'choices' => $this->question->choices(),
            'order' => $this->question->order,
            'status' => LiveQuestion::STATUS_OPEN,
        ];
    }
}
