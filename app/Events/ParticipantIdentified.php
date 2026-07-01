<?php

namespace App\Events;

use App\Models\LiveParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantIdentified implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveParticipant $participant) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('live-session.'.$this->participant->session->slug),
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.identified';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->participant->id,
            'name' => $this->participant->name,
            'photo_url' => $this->participant->photo_url,
            'is_master' => $this->participant->is_master,
        ];
    }
}
