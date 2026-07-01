<?php

namespace App\Events;

use App\Models\LiveParticipant;
use App\Models\LiveQuestion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterVoted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveQuestion $question,
        public LiveParticipant $participant,
        public string $choice,
    ) {}

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
        return 'master.voted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'choice' => $this->choice,
            'name' => $this->participant->name,
            'photo_url' => $this->participant->photo_url,
        ];
    }
}
