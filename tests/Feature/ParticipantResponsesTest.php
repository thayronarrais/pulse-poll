<?php

namespace Tests\Feature;

use App\Models\LiveParticipant;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\LiveVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantResponsesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): LiveSession
    {
        return LiveSession::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);
    }

    public function test_maps_each_participant_to_their_choice_per_question(): void
    {
        $session = $this->makeSession();
        $q1 = $session->questions()->create(['choice_type' => 'ab', 'status' => LiveQuestion::STATUS_CLOSED, 'order' => 0]);
        $q2 = $session->questions()->create(['choice_type' => 'abcd', 'status' => LiveQuestion::STATUS_OPEN, 'order' => 1]);

        $joao = LiveParticipant::create(['live_session_id' => $session->id, 'device_token' => 'tok-joao', 'name' => 'João']);
        $maria = LiveParticipant::create(['live_session_id' => $session->id, 'device_token' => 'tok-maria', 'name' => 'Maria']);

        LiveVote::create(['live_question_id' => $q1->id, 'device_token' => 'tok-joao', 'choice' => 'A']);
        LiveVote::create(['live_question_id' => $q2->id, 'device_token' => 'tok-joao', 'choice' => 'C']);
        LiveVote::create(['live_question_id' => $q1->id, 'device_token' => 'tok-maria', 'choice' => 'B']);
        // Maria não votou em q2.

        $map = $session->participantResponses();

        $this->assertSame('A', $map[$joao->id][$q1->id]);
        $this->assertSame('C', $map[$joao->id][$q2->id]);
        $this->assertSame('B', $map[$maria->id][$q1->id]);
        $this->assertArrayNotHasKey($q2->id, $map[$maria->id]);
    }

    public function test_ignores_votes_from_devices_that_are_not_participants(): void
    {
        $session = $this->makeSession();
        $q1 = $session->questions()->create(['choice_type' => 'ab', 'status' => LiveQuestion::STATUS_OPEN, 'order' => 0]);
        LiveVote::create(['live_question_id' => $q1->id, 'device_token' => 'anon-token', 'choice' => 'A']);

        $this->assertSame([], $session->participantResponses());
    }
}
