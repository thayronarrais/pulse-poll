# Destacar a escolha de um participante Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Na tela de controle ao vivo, clicar num participante destaca em cada card de pergunta qual alternativa ele escolheu; clicar de novo limpa o destaque.

**Architecture:** Um método de modelo (`LiveSession::participantResponses()`) monta o mapa `participantId => [questionId => choice]` juntando votos a participantes por `device_token`. Um endpoint admin sob demanda devolve esse mapa em JSON. O painel Alpine carrega o mapa inicial via config e o re-busca ao selecionar um participante, destacando a escolha em cada card. O canal público realtime não é tocado (sigilo do voto preservado).

**Tech Stack:** Laravel 13, PHPUnit 12, Alpine.js, Tailwind 4, Vite.

## Global Constraints

- PHP 8.4: chaves em todas as estruturas de controle; tipos de retorno e de parâmetros explícitos; PHPDoc com array shapes.
- Rodar `vendor/bin/pint --dirty --format agent` após mudar arquivos PHP.
- Testes são PHPUnit (`php artisan make:test --phpunit`), feature tests com `RefreshDatabase`.
- Este diretório **não é um repositório git** — pule os passos de `git commit` (deixados no plano por convenção; apenas marque como concluído).
- A ligação voto→participante é por `device_token` dentro da sessão. Votos: `LiveVote` (`live_question_id`, `device_token`, `voter_name`, `choice`). Participantes: `LiveParticipant` (`live_session_id`, `device_token`, `name`).
- Autorização da sessão: `abort_unless($liveSession->user_id === auth()->id(), 403)` (já existe como `authorizeSession`).

---

### Task 1: `LiveSession::participantResponses()`

Monta o mapa de respostas por participante.

**Files:**
- Modify: `app/Models/LiveSession.php`
- Test: `tests/Feature/ParticipantResponsesTest.php`

**Interfaces:**
- Produces: `LiveSession::participantResponses(): array` retornando `array<int, array<int, string>>` no formato `[participantId => [questionId => choice]]`. Participante sem voto numa pergunta não tem entrada para aquela pergunta. Sessão sem participantes retorna `[]`.

- [ ] **Step 1: Write the failing test**

Crie `tests/Feature/ParticipantResponsesTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ParticipantResponsesTest`
Expected: FAIL com "Call to undefined method App\Models\LiveSession::participantResponses()".

- [ ] **Step 3: Write minimal implementation**

Em `app/Models/LiveSession.php`, adicione o método dentro da classe (após `isDraft()`):

```php
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
```

`LiveVote` está no mesmo namespace `App\Models`, então não precisa de `use`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ParticipantResponsesTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/LiveSession.php tests/Feature/ParticipantResponsesTest.php
git commit -m "feat: add participantResponses map to LiveSession"
```
(Não é repo git — apenas rode o Pint e marque concluído.)

---

### Task 2: Endpoint admin `responses`

Expõe o mapa por HTTP, só para o dono da sessão.

**Files:**
- Modify: `app/Http/Controllers/Admin/LiveControlController.php`
- Modify: `routes/web.php:48` (junto às demais rotas `live-sessions.*`)
- Test: `tests/Feature/LiveControlTest.php`

**Interfaces:**
- Consumes: `LiveSession::participantResponses()` (Task 1).
- Produces: rota nomeada `admin.live-sessions.responses` (GET) → `LiveControlController@responses(LiveSession $liveSession): JsonResponse`, JSON do mapa. Dono: 200; não-dono: 403; convidado: redirect login.

- [ ] **Step 1: Write the failing tests**

Adicione estes três testes ao final da classe em `tests/Feature/LiveControlTest.php` (antes do `}` final). Eles usam o helper `sessionForUser` já existente, que cria 2 perguntas (`ab` e `abcd`).

```php
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

        $participant = \App\Models\LiveParticipant::create([
            'live_session_id' => $session->id,
            'device_token' => 'tok-1',
            'name' => 'João',
        ]);
        \App\Models\LiveVote::create([
            'live_question_id' => $question->id,
            'device_token' => 'tok-1',
            'choice' => 'A',
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.live-sessions.responses', $session))
            ->assertOk()
            ->assertJson([$participant->id => [$question->id => 'A']]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=LiveControlTest`
Expected: FAIL com erro de rota não definida (`Route [admin.live-sessions.responses] not defined`).

- [ ] **Step 3: Add the route**

Em `routes/web.php`, logo após a linha do `control` (atual linha 48), adicione:

```php
    Route::get('live-sessions/{liveSession}/responses', [LiveControlController::class, 'responses'])->name('live-sessions.responses');
```

- [ ] **Step 4: Add the controller method**

Em `app/Http/Controllers/Admin/LiveControlController.php`, adicione o método após `panel()`:

```php
    public function responses(LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($liveSession);

        return response()->json($liveSession->participantResponses());
    }
```

`JsonResponse` e `LiveSession` já estão importados no arquivo.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=LiveControlTest`
Expected: PASS (todos os testes do arquivo, incluindo os 3 novos).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/LiveControlController.php routes/web.php tests/Feature/LiveControlTest.php
git commit -m "feat: add admin responses endpoint for participant choices"
```
(Não é repo git — apenas rode o Pint e marque concluído.)

---

### Task 3: Destaque no painel (config + Alpine + Blade)

Passa o mapa inicial e a URL para o painel, e implementa a seleção/destaque na UI. Sem testes automatizados (frontend) — verificação por build e checklist manual.

**Files:**
- Modify: `resources/views/admin/live/control.blade.php`
- Modify: `resources/js/live/control.js`

**Interfaces:**
- Consumes: `admin.live-sessions.responses` (Task 2) via `config.responsesUrl`; mapa inicial via `config.responses`.

- [ ] **Step 1: Passar `responses` e `responsesUrl` no config**

Em `resources/views/admin/live/control.blade.php`, dentro do array `$config` (bloco `@php`), adicione duas chaves após `'finishUrl' => ...` (qualquer posição dentro do array serve):

```php
        'responsesUrl' => route('admin.live-sessions.responses', $liveSession),
        'responses' => $liveSession->participantResponses(),
```

- [ ] **Step 2: Estado e métodos no Alpine**

Em `resources/js/live/control.js`, adicione as propriedades de estado logo após `currentQuestionId: config.currentQuestionId,`:

```js
        responsesUrl: config.responsesUrl,
        responses: config.responses || {},
        selectedParticipantId: null,
```

Depois, adicione estes métodos dentro do objeto retornado (por exemplo logo após `upsertParticipant(e) { ... },`):

```js
        async selectParticipant(p) {
            if (this.selectedParticipantId === p.id) {
                this.selectedParticipantId = null;
                return;
            }
            this.selectedParticipantId = p.id;
            await this.fetchResponses();
        },

        async fetchResponses() {
            try {
                const res = await fetch(this.responsesUrl, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    credentials: 'same-origin',
                });
                if (res.ok) this.responses = await res.json();
            } catch (e) {
                // Mantém o mapa carregado com a página como fallback.
            }
        },

        pickFor(q) {
            if (this.selectedParticipantId === null) return null;
            const r = this.responses[this.selectedParticipantId];
            return r ? (r[q.id] ?? null) : null;
        },

        selectedName() {
            const p = this.participants.find((x) => x.id === this.selectedParticipantId);
            return p ? p.name : '';
        },
```

- [ ] **Step 3: Card do participante clicável + realce**

Em `resources/views/admin/live/control.blade.php`, no `<template x-for="p in participants">`, altere a `<div>` do card para selecionar ao clicar e realçar quando selecionado. Substitua a div de abertura do card:

```html
                <div class="flex items-center gap-3 border rounded-lg p-3"
                     :class="p.is_master ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
```

por:

```html
                <div @click="selectParticipant(p)"
                     class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer transition"
                     :class="{
                        'ring-2 ring-indigo-400 border-indigo-400': selectedParticipantId === p.id,
                        'border-amber-400 bg-amber-50': p.is_master && selectedParticipantId !== p.id,
                        'border-gray-200': !p.is_master && selectedParticipantId !== p.id
                     }">
```

E no botão "Tornar/Remover Master" dentro desse card, adicione `.stop` ao clique para não disparar a seleção. Troque `@click="toggleMaster(p)"` por:

```html
                    @click.stop="toggleMaster(p)"
```

Atualize também o texto de ajuda do bloco (parágrafo abaixo do `<h2>Participantes`) para mencionar o clique. Substitua o `<p>` existente por:

```html
        <p class="text-sm text-gray-500 mb-4">Quem se identificou. Clique numa pessoa para ver as escolhas dela em cada pergunta. Marque alguém como <strong>Master</strong> para que as opções escolhidas por essa pessoa apareçam como sugestão para os demais.</p>
```

- [ ] **Step 4: Barra "vendo respostas de X" acima das perguntas**

Ainda em `control.blade.php`, logo antes de `<div class="space-y-4">` (a lista de perguntas), adicione:

```html
    <div x-show="selectedParticipantId !== null" style="display:none;"
         class="mb-3 flex items-center justify-between gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2 text-sm">
        <span class="text-indigo-800">Mostrando as escolhas de <strong x-text="selectedName()"></strong> em cada pergunta.</span>
        <button type="button" @click="selectedParticipantId = null"
                class="text-indigo-700 hover:text-indigo-900 font-semibold">Limpar</button>
    </div>
```

- [ ] **Step 5: Destaque da escolha em cada card de pergunta**

No bloco das barras de tally (`<template x-for="(choice, idx) in q.choices">`), adicione um badge na linha do rótulo. Substitua o bloco:

```html
                            <div class="flex justify-between text-sm font-medium mb-1">
                                <span x-text="choice"></span>
                                <span x-text="pct(q, choice) + '% (' + (q.tally?.[choice] ?? 0) + ')'"></span>
                            </div>
```

por:

```html
                            <div class="flex justify-between text-sm font-medium mb-1">
                                <span class="flex items-center gap-2">
                                    <span x-text="choice"></span>
                                    <span x-show="pickFor(q) === choice" style="display:none;"
                                          class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 bg-indigo-100 rounded-full px-2 py-0.5">
                                        ✓ <span x-text="selectedName()"></span>
                                    </span>
                                </span>
                                <span x-text="pct(q, choice) + '% (' + (q.tally?.[choice] ?? 0) + ')'"></span>
                            </div>
```

Em seguida, logo após o `<p class="text-right text-xs text-gray-400 mt-1" x-text="'Total: ' + total(q) + ' votos'"></p>`, adicione a indicação de "não votou":

```html
                    <p x-show="selectedParticipantId !== null && pickFor(q) === null" style="display:none;"
                       class="text-right text-xs text-gray-400">
                        <span x-text="selectedName()"></span> não votou nesta pergunta.
                    </p>
```

- [ ] **Step 6: Build do frontend**

Run: `npm run build`
Expected: build conclui sem erros e gera os assets em `public/build`.

- [ ] **Step 7: Verificação manual (checklist)**

Abra `admin/live-sessions/{slug}/control` numa sessão com participantes e votos e confirme:
- Clicar num participante realça o card dele (anel indigo) e aparece a barra "Mostrando as escolhas de X".
- Em cada pergunta onde ele votou, a alternativa escolhida mostra o badge "✓ X".
- Em pergunta onde ele não votou, aparece "X não votou nesta pergunta".
- Clicar no botão Master não seleciona o participante (`.stop` funcionando).
- Clicar de novo no mesmo participante (ou em "Limpar") remove o destaque.

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/live/control.blade.php resources/js/live/control.js public/build
git commit -m "feat: highlight a participant's choices on the live control panel"
```
(Não é repo git — apenas marque concluído.)

---

## Notas de verificação final

- Suite relacionada: `php artisan test --compact --filter=LiveControlTest` e `php artisan test --compact --filter=ParticipantResponsesTest`.
- Após aprovar as tarefas, pergunte ao usuário se quer rodar a suíte completa (`php artisan test --compact`).
