<?php

namespace Tests\Feature;

use App\Events\MasterVoted;
use App\Events\ParticipantIdentified;
use App\Models\LiveParticipant;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveParticipantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: LiveSession, 1: LiveQuestion}
     */
    private function liveSessionWithOpenQuestion(): array
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'title' => 'Sessão Teste',
            'slug' => 'sessao-teste-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);
        $question = $session->questions()->create([
            'choice_type' => 'ab',
            'status' => LiveQuestion::STATUS_OPEN,
            'order' => 0,
        ]);
        $session->update(['current_question_id' => $question->id]);

        return [$session, $question];
    }

    public function test_identify_creates_a_participant_with_name(): void
    {
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.identify', $session), ['name' => '  João  '])
            ->assertOk()
            ->assertJson(['ok' => true, 'name' => 'João']);

        $this->assertDatabaseHas('live_participants', [
            'live_session_id' => $session->id,
            'device_token' => 'device-1',
            'name' => 'João',
            'is_master' => false,
        ]);
    }

    public function test_identify_broadcasts_participant_identified(): void
    {
        Event::fake();
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.identify', $session), ['name' => 'João'])
            ->assertOk();

        Event::assertDispatched(ParticipantIdentified::class, function (ParticipantIdentified $event) {
            return $event->participant->name === 'João';
        });
    }

    public function test_identify_requires_a_name(): void
    {
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.identify', $session), ['name' => ''])
            ->assertStatus(422);
    }

    public function test_identify_stores_uploaded_photo(): void
    {
        Storage::fake('public');
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->post(route('live.identify', $session), [
                'name' => 'Maria',
                'photo' => UploadedFile::fake()->image('selfie.jpg'),
            ])
            ->assertOk();

        $participant = LiveParticipant::where('device_token', 'device-1')->first();
        $this->assertNotNull($participant->photo_path);
        Storage::disk('public')->assertExists($participant->photo_path);
    }

    public function test_identify_succeeds_even_when_photo_is_not_an_image(): void
    {
        Storage::fake('public');
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->post(route('live.identify', $session), [
                'name' => 'João',
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $participant = LiveParticipant::where('device_token', 'device-1')->first();
        $this->assertNotNull($participant);
        $this->assertSame('João', $participant->name);
        $this->assertNull($participant->photo_path);
    }

    public function test_identify_updates_existing_participant_for_same_device(): void
    {
        [$session] = $this->liveSessionWithOpenQuestion();

        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.identify', $session), ['name' => 'João'])->assertOk();
        $this->withCredentials()->withCookie('live_device_token', 'device-1')
            ->postJson(route('live.identify', $session), ['name' => 'João Silva'])->assertOk();

        $this->assertEquals(1, LiveParticipant::where('device_token', 'device-1')->count());
        $this->assertDatabaseHas('live_participants', ['device_token' => 'device-1', 'name' => 'João Silva']);
    }

    public function test_master_vote_broadcasts_master_voted_event(): void
    {
        Event::fake();
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $session->participants()->create([
            'device_token' => 'master-device',
            'name' => 'João',
            'is_master' => true,
        ]);

        $this->withCredentials()->withCookie('live_device_token', 'master-device')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertOk();

        Event::assertDispatched(MasterVoted::class, function (MasterVoted $event) use ($question) {
            return $event->question->is($question) && $event->choice === 'A' && $event->participant->name === 'João';
        });
    }

    public function test_non_master_vote_does_not_broadcast_master_voted(): void
    {
        Event::fake();
        [$session] = $this->liveSessionWithOpenQuestion();

        $session->participants()->create([
            'device_token' => 'plain-device',
            'name' => 'Ana',
            'is_master' => false,
        ]);

        $this->withCredentials()->withCookie('live_device_token', 'plain-device')
            ->postJson(route('live.vote', $session), ['choice' => 'A'])
            ->assertOk();

        Event::assertNotDispatched(MasterVoted::class);
    }

    public function test_state_includes_master_picks_for_current_question(): void
    {
        [$session, $question] = $this->liveSessionWithOpenQuestion();

        $session->participants()->create([
            'device_token' => 'master-device',
            'name' => 'João',
            'is_master' => true,
        ]);
        $question->votes()->create(['device_token' => 'master-device', 'choice' => 'A']);

        $this->withCredentials()->withCookie('live_device_token', 'other-device')
            ->getJson(route('live.state', $session))
            ->assertOk()
            ->assertJsonPath('master_picks.A.0.name', 'João');
    }

    public function test_admin_can_toggle_master(): void
    {
        $owner = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);
        $participant = $session->participants()->create([
            'device_token' => 'device-1',
            'name' => 'João',
            'is_master' => false,
        ]);

        $this->actingAs($owner)
            ->postJson(route('admin.live-sessions.participants.toggle-master', [$session, $participant]))
            ->assertOk()
            ->assertJson(['is_master' => true]);

        $this->assertDatabaseHas('live_participants', ['id' => $participant->id, 'is_master' => true]);
    }

    public function test_toggle_master_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $owner->id,
            'title' => 'Sessão',
            'slug' => 'sessao-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);
        $participant = $session->participants()->create([
            'device_token' => 'device-1',
            'name' => 'João',
            'is_master' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.live-sessions.participants.toggle-master', [$session, $participant]))
            ->assertStatus(403);
    }
}
