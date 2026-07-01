<?php

namespace Tests\Feature;

use App\Events\LiveSessionStateChanged;
use App\Events\QuestionClosed;
use App\Events\QuestionOpened;
use App\Events\QuestionTextUpdated;
use App\Models\LiveParticipant;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\LiveVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveControlTest extends TestCase
{
    use RefreshDatabase;

    private function sessionForUser(User $user): LiveSession
    {
        $session = LiveSession::create([
            'user_id' => $user->id,
            'title' => 'Sessão Teste',
            'slug' => 'sessao-teste-'.uniqid(),
            'status' => LiveSession::STATUS_DRAFT,
        ]);
        $session->questions()->create(['choice_type' => 'ab', 'status' => LiveQuestion::STATUS_PENDING, 'order' => 0]);
        $session->questions()->create(['choice_type' => 'abcd', 'status' => LiveQuestion::STATUS_PENDING, 'order' => 1]);

        return $session;
    }

    public function test_open_question_requires_auth(): void
    {
        $session = $this->sessionForUser(User::factory()->create());
        $question = $session->questions()->first();

        // Web auth middleware redirects guests to login.
        $this->post(route('admin.live-sessions.questions.open', [$session, $question]))
            ->assertRedirect(route('login'));
    }

    public function test_open_question_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.live-sessions.questions.open', [$session, $question]))
            ->assertStatus(403);
    }

    public function test_opening_a_question_closes_the_previous_one(): void
    {
        Event::fake();
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        [$first, $second] = $session->questions->all();

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.open', [$session, $first]))
            ->assertOk();
        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.open', [$session, $second]))
            ->assertOk();

        $this->assertEquals(LiveQuestion::STATUS_CLOSED, $first->fresh()->status);
        $this->assertEquals(LiveQuestion::STATUS_OPEN, $second->fresh()->status);
        $this->assertEquals($second->id, $session->fresh()->current_question_id);
        Event::assertDispatched(QuestionOpened::class, 2);
    }

    public function test_close_question_dispatches_event(): void
    {
        Event::fake();
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.open', [$session, $question]))->assertOk();
        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.close', [$session, $question]))->assertOk();

        $this->assertEquals(LiveQuestion::STATUS_CLOSED, $question->fresh()->status);
        Event::assertDispatched(QuestionClosed::class);
    }

    public function test_finish_closes_session(): void
    {
        Event::fake();
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.finish', $session))->assertOk();

        $session->refresh();
        $this->assertEquals(LiveSession::STATUS_FINISHED, $session->status);
        $this->assertNull($session->current_question_id);
        Event::assertDispatched(LiveSessionStateChanged::class);
    }

    public function test_ask_question_creates_and_opens_it(): void
    {
        Event::fake();
        $owner = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_DRAFT,
        ]);

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.ask', $session), ['choice_type' => 'simnao'])
            ->assertOk()
            ->assertJson(['choice_type' => 'simnao', 'status' => 'open', 'choices' => ['SIM', 'NAO']]);

        $session->refresh();
        $this->assertEquals(LiveSession::STATUS_LIVE, $session->status);
        $this->assertEquals(1, $session->questions()->count());
        $question = $session->questions()->first();
        $this->assertEquals('open', $question->status);
        $this->assertEquals($question->id, $session->current_question_id);
        Event::assertDispatched(QuestionOpened::class);
    }

    public function test_asking_a_new_question_closes_the_previous(): void
    {
        $owner = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);

        $this->actingAs($owner)->postJson(route('admin.live-sessions.questions.ask', $session), ['choice_type' => 'ab'])->assertOk();
        $first = $session->questions()->first();

        $this->actingAs($owner)->postJson(route('admin.live-sessions.questions.ask', $session), ['choice_type' => 'abcd'])->assertOk();

        $this->assertEquals('closed', $first->fresh()->status);
        $this->assertEquals(2, $session->questions()->count());
    }

    public function test_ask_question_rejects_invalid_type(): void
    {
        $owner = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_DRAFT,
        ]);

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.ask', $session), ['choice_type' => 'xyz'])
            ->assertStatus(422);
    }

    public function test_question_reference_text_can_be_edited(): void
    {
        Event::fake();
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();

        $this->actingAs($owner)
            ->patchJson(route('admin.live-sessions.questions.update', [$session, $question]), ['admin_text' => 'Aprova a proposta?'])
            ->assertOk()
            ->assertJson(['admin_text' => 'Aprova a proposta?']);

        $this->assertEquals('Aprova a proposta?', $question->fresh()->admin_text);
        Event::assertDispatched(QuestionTextUpdated::class, fn (QuestionTextUpdated $event) => $event->question->is($question));
    }

    public function test_editing_question_text_broadcasts_it_to_voters(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();
        $question->update(['admin_text' => 'João estas a ver essa pergunta?']);

        $event = new QuestionTextUpdated($question);

        $this->assertEquals('question.text-updated', $event->broadcastAs());
        $this->assertEquals([
            'question_id' => $question->id,
            'text' => 'João estas a ver essa pergunta?',
        ], $event->broadcastWith());
        $this->assertEquals('live-session.'.$session->slug, $event->broadcastOn()[0]->name);
    }

    public function test_voter_state_includes_question_text(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();
        $question->update(['status' => LiveQuestion::STATUS_OPEN, 'admin_text' => 'Aprova a proposta?']);
        $session->update(['status' => LiveSession::STATUS_LIVE, 'current_question_id' => $question->id]);

        $this->getJson(route('live.state', $session))
            ->assertOk()
            ->assertJsonPath('question.text', 'Aprova a proposta?');
    }

    public function test_control_panel_renders(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);

        $this->actingAs($owner)
            ->get(route('admin.live-sessions.control', $session))
            ->assertOk()
            ->assertSee($session->title);
    }

    public function test_voter_screen_renders_and_sets_cookie(): void
    {
        $session = $this->sessionForUser(User::factory()->create());

        $response = $this->get(route('live.show', $session));

        $response->assertOk()->assertSee($session->title);
        $response->assertCookie('live_device_token');
    }

    public function test_question_from_another_session_is_not_resolvable(): void
    {
        $owner = User::factory()->create();
        $sessionA = $this->sessionForUser($owner);
        $sessionB = $this->sessionForUser($owner);
        $questionB = $sessionB->questions()->first();

        // scopeBindings: questionB does not belong to sessionA -> 404
        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.questions.open', [$sessionA, $questionB]))
            ->assertStatus(404);
    }

    public function test_responses_requires_auth(): void
    {
        $session = $this->sessionForUser(User::factory()->create());

        $this->get(route('admin.live-sessions.responses', $session))
            ->assertRedirect(route('login'));
    }

    public function test_responses_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.live-sessions.responses', $session))
            ->assertStatus(403);
    }

    public function test_responses_returns_participant_choice_map(): void
    {
        $owner = User::factory()->create();
        $session = $this->sessionForUser($owner);
        $question = $session->questions()->first();

        $participant = LiveParticipant::create([
            'live_session_id' => $session->id,
            'device_token' => 'tok-1',
            'name' => 'João',
        ]);
        LiveVote::create([
            'live_question_id' => $question->id,
            'device_token' => 'tok-1',
            'choice' => 'A',
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.live-sessions.responses', $session))
            ->assertOk()
            ->assertJson([$participant->id => [$question->id => 'A']]);
    }
}
