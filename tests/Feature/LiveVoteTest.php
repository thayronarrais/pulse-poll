<?php

namespace Tests\Feature;

use App\Events\ResultsUpdated;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\LiveVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveVoteTest extends TestCase
{
    use RefreshDatabase;

    private function liveSessionWithOpenQuestion(string $choiceType = 'ab'): array
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'title' => 'Sessão Teste',
            'slug' => 'sessao-teste-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);
        $question = $session->questions()->create([
            'choice_type' => $choiceType,
            'status' => LiveQuestion::STATUS_OPEN,
            'order' => 0,
        ]);
        $session->update(['current_question_id' => $question->id]);

        return [$session, $question];
    }

    public function test_valid_vote_is_recorded(): void
    {
        Event::fake();
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $response = $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('live_votes', [
            'live_question_id' => $question->id,
            'device_token' => 'device-1',
            'choice' => 'A',
        ]);
        Event::assertDispatched(ResultsUpdated::class);
    }

    public function test_optional_voter_name_is_recorded_when_provided(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A', 'name' => '  Maria  '])
            ->assertOk();

        $this->assertDatabaseHas('live_votes', [
            'live_question_id' => $question->id,
            'device_token' => 'device-1',
            'voter_name' => 'Maria',
            'choice' => 'A',
        ]);
    }

    public function test_vote_without_name_stores_null_voter_name(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertOk();

        $this->assertDatabaseHas('live_votes', [
            'live_question_id' => $question->id,
            'voter_name' => null,
        ]);
    }

    public function test_double_vote_from_same_device_is_rejected(): void
    {
        Event::fake();
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertOk();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'B'])
            ->assertStatus(409);

        $this->assertEquals(1, LiveVote::where('live_question_id', $question->id)->count());
    }

    public function test_different_devices_count_as_two_votes(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])->assertOk();
        $this->withCredentials()->withCookie('live_device_token', 'device-2')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])->assertOk();

        $this->assertEquals(2, LiveVote::where('live_question_id', $question->id)->count());
    }

    public function test_vote_on_closed_question_is_rejected(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();
        $question->update(['status' => LiveQuestion::STATUS_CLOSED]);

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertStatus(409);
    }

    public function test_vote_when_session_not_live_is_rejected(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();
        $session->update(['status' => LiveSession::STATUS_FINISHED]);

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertStatus(409);
    }

    public function test_invalid_choice_is_rejected(): void
    {
        [$session] = $this->liveSessionWithOpenQuestion('ab');

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'Z'])
            ->assertStatus(422);
    }

    public function test_simnao_accepts_sim_and_rejects_letter(): void
    {
        [$session] = $this->liveSessionWithOpenQuestion('simnao');

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.vote', $session), ['choice' => 'SIM'])->assertOk();

        $this->withCredentials()->withCookie('live_device_token', 'device-2')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])->assertStatus(422);
    }
}
