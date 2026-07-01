<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSessionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_can_be_created_with_title_only(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.live-sessions.store'), [
            'title' => 'Plenária 2026',
        ]);

        $session = LiveSession::where('title', 'Plenária 2026')->first();
        $response->assertRedirect(route('admin.live-sessions.control', $session));
        $this->assertEquals($user->id, $session->user_id);
        $this->assertEquals(LiveSession::STATUS_DRAFT, $session->status);
        $this->assertEquals(0, $session->questions()->count());
        $this->assertStringContainsString('plenaria-2026', $session->slug);
    }

    public function test_creating_requires_a_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.live-sessions.store'), [])
            ->assertSessionHasErrors('title');
    }

    public function test_draft_session_title_can_be_updated(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'title' => 'Antigo',
            'slug' => 'antigo-'.uniqid(),
            'status' => LiveSession::STATUS_DRAFT,
        ]);

        $this->actingAs($user)->put(route('admin.live-sessions.update', $session), [
            'title' => 'Novo Título',
        ])->assertRedirect(route('admin.live-sessions.index'));

        $this->assertEquals('Novo Título', $session->fresh()->title);
    }

    public function test_editing_is_blocked_when_not_draft(): void
    {
        $user = User::factory()->create();
        $session = LiveSession::create([
            'user_id' => $user->id,
            'title' => 'Em andamento',
            'slug' => 'em-andamento-'.uniqid(),
            'status' => LiveSession::STATUS_LIVE,
        ]);

        $this->actingAs($user)->get(route('admin.live-sessions.edit', $session))
            ->assertStatus(403);
    }

    public function test_user_only_sees_own_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        LiveSession::create(['user_id' => $other->id, 'title' => 'Alheia', 'slug' => 'alheia-'.uniqid(), 'status' => 'draft']);

        $this->actingAs($user)->get(route('admin.live-sessions.index'))
            ->assertOk()
            ->assertDontSee('Alheia');
    }
}
