@extends('layouts.admin-live')

@php
    $colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981'];
    $config = [
        'slug' => $liveSession->slug,
        'csrf' => csrf_token(),
        'participantUrl' => route('live.show', $liveSession),
        'startUrl' => route('admin.live-sessions.start', $liveSession),
        'finishUrl' => route('admin.live-sessions.finish', $liveSession),
        'responsesUrl' => route('admin.live-sessions.responses', $liveSession),
        'responses' => $liveSession->participantResponses(),
        'askUrl' => route('admin.live-sessions.questions.ask', $liveSession),
        'sessionStatus' => $liveSession->status,
        'currentQuestionId' => $liveSession->current_question_id,
        'participants' => $liveSession->participants->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'photo_url' => $p->photo_url,
            'is_master' => $p->is_master,
            'toggleUrl' => route('admin.live-sessions.participants.toggle-master', [$liveSession, $p]),
        ])->values(),
        'toggleMasterUrlTemplate' => route('admin.live-sessions.participants.toggle-master', [$liveSession, '__ID__']),
        'questions' => $liveSession->questions->sortByDesc('order')->map(fn ($q) => [
            'id' => $q->id,
            'admin_text' => $q->admin_text,
            'choice_type' => $q->choice_type,
            'choices' => $q->choices(),
            'status' => $q->status,
            'order' => $q->order,
            'tally' => $q->tally(),
            'openUrl' => route('admin.live-sessions.questions.open', [$liveSession, $q]),
            'closeUrl' => route('admin.live-sessions.questions.close', [$liveSession, $q]),
            'updateUrl' => route('admin.live-sessions.questions.update', [$liveSession, $q]),
        ])->values(),
    ];
@endphp

@section('content')
<div x-data="liveControl(@js($config))">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $liveSession->title }}</h1>
            <div class="mt-3 flex flex-col sm:flex-row sm:items-start gap-4">
                {{-- QR code (generated for the participant link) --}}
                <img x-show="qrSrc" :src="qrSrc" alt="{{ __('admin.control.qr_alt') }}" width="160" height="160"
                     class="w-40 h-40 rounded-lg border border-gray-200 bg-white p-2 shadow-sm flex-shrink-0"
                     style="image-rendering: pixelated; display: none;">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-700 mb-1">{{ __('admin.control.link_to_participants') }}</p>
                    <a href="{{ route('live.show', $liveSession) }}" target="_blank"
                       class="text-sm text-indigo-600 hover:underline break-all">{{ route('live.show', $liveSession) }}</a>
                    <div class="mt-2">
                        <button type="button" @click="copyLink()"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg shadow-sm transition">
                            <span x-show="!linkCopied">📋 {{ __('admin.control.copy_link') }}</span>
                            <span x-show="linkCopied" style="display:none;">✓ {{ __('admin.control.copied') }}</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">{{ __('admin.control.qr_hint') }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm font-semibold"
                  :class="{'bg-gray-200 text-gray-700': sessionStatus==='draft', 'bg-green-100 text-green-700': sessionStatus==='live', 'bg-red-100 text-red-700': sessionStatus==='finished'}"
                  x-text="{draft: @js(__('admin.control.status_draft')), live: @js(__('admin.control.status_live')), finished: @js(__('admin.control.status_finished'))}[sessionStatus]"></span>
            <button type="button" @click="finish()" x-show="sessionStatus === 'live'" :disabled="busy"
                    class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg shadow-sm disabled:opacity-50">{{ __('admin.control.finish') }}</button>
        </div>
    </div>

    {{-- Ask a new question on the fly: pick the alternatives type and release it now --}}
    <div x-show="sessionStatus !== 'finished'" class="bg-white shadow-sm rounded-lg border border-indigo-200 p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-1">{{ __('admin.control.new_question') }}</h2>
        <p class="text-sm text-gray-500 mb-4">{{ __('admin.control.new_question_hint') }}</p>
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="ask('ab')" :disabled="busy"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-sm disabled:opacity-50">A, B</button>
            <button type="button" @click="ask('abcd')" :disabled="busy"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-sm disabled:opacity-50">A, B, C, D</button>
            <button type="button" @click="ask('simnao')" :disabled="busy"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-sm disabled:opacity-50">{{ __('admin.control.choice_yes_no') }}</button>
        </div>
    </div>

    {{-- Identified participants: mark anyone as "Master" so their picks are suggested to others --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-1">{{ __('admin.control.participants') }}</h2>
        <p class="text-sm text-gray-500 mb-4">{!! __('admin.control.participants_hint') !!}</p>

        <div x-show="participants.length === 0" class="text-sm text-gray-400 py-2">
            {{ __('admin.control.nobody_identified') }}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="p in participants" :key="p.id">
                <div @click="selectParticipant(p)"
                     class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer transition"
                     :class="{
                        'ring-2 ring-indigo-400 border-indigo-400': selectedParticipantId === p.id,
                        'border-amber-400 bg-amber-50': p.is_master && selectedParticipantId !== p.id,
                        'border-gray-200': !p.is_master && selectedParticipantId !== p.id
                     }">
                    <img :src="p.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(p.name) + '&background=random&color=fff&bold=true&size=64')"
                         class="w-11 h-11 rounded-full object-cover bg-gray-200 flex-shrink-0" :alt="p.name">
                    <div class="flex-grow min-w-0">
                        <p class="font-medium text-gray-800 truncate" x-text="p.name"></p>
                        <p x-show="p.is_master" class="text-xs font-semibold text-amber-600">{{ __('admin.control.master_badge') }}</p>
                    </div>
                    <button type="button" @click.stop="toggleMaster(p)" :disabled="busy"
                            class="text-sm font-semibold py-1.5 px-3 rounded disabled:opacity-50 flex-shrink-0"
                            :class="p.is_master ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                            x-text="p.is_master ? @js(__('admin.control.remove_master')) : @js(__('admin.control.make_master'))"></button>
                </div>
            </template>
        </div>
    </div>

    <div x-show="questions.length === 0" class="text-center py-10 text-gray-400">
        {{ __('admin.control.no_questions') }}
    </div>

    <div x-show="selectedParticipantId !== null" style="display:none;"
         class="mb-3 flex items-center justify-between gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2 text-sm">
        <span class="text-indigo-800">{{ __('admin.control.showing_choices_prefix') }} <strong x-text="selectedName()"></strong> {{ __('admin.control.showing_choices_suffix') }}</span>
        <button type="button" @click="selectedParticipantId = null"
                class="text-indigo-700 hover:text-indigo-900 font-semibold">{{ __('admin.control.clear') }}</button>
    </div>

    <div class="space-y-4">
        <template x-for="(q, qIndex) in questions" :key="q.id">
            <div class="bg-white shadow-sm rounded-lg border p-5"
                 :class="q.id === currentQuestionId ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200'">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                    <div class="flex-grow">
                        <span class="text-xs font-semibold text-gray-400 uppercase">{{ __('admin.control.question_label') }} <span x-text="q.order + 1"></span></span>

                        {{-- Reference text: display + inline edit --}}
                        <div x-show="!q._editing" class="flex items-center gap-2">
                            <p class="text-lg font-medium text-gray-800" x-text="q.admin_text || @js(__('admin.control.no_reference_text'))"></p>
                            <button type="button" @click="startEdit(q)" class="text-gray-400 hover:text-indigo-600 text-sm" title="{{ __('admin.control.edit_text_title') }}">✏️</button>
                        </div>
                        <div x-show="q._editing" class="flex items-center gap-2 mt-1" style="display:none;">
                            <input type="text" x-model="q._draft" @keydown.enter.prevent="saveEdit(q)" @keydown.escape="cancelEdit(q)"
                                   placeholder="{{ __('admin.control.reference_text_placeholder') }}"
                                   class="flex-grow rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
                            <button type="button" @click="saveEdit(q)" :disabled="busy" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-1.5 px-3 rounded disabled:opacity-50">{{ __('admin.control.save') }}</button>
                            <button type="button" @click="cancelEdit(q)" class="text-gray-500 hover:text-gray-700 text-sm py-1.5 px-2">{{ __('admin.control.cancel') }}</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded text-xs font-semibold"
                              :class="{'bg-gray-100 text-gray-600': q.status==='pending', 'bg-green-100 text-green-700': q.status==='open', 'bg-gray-300 text-gray-700': q.status==='closed'}"
                              x-text="{pending: @js(__('admin.control.status_pending')), open: @js(__('admin.control.status_open')), closed: @js(__('admin.control.status_closed'))}[q.status]"></span>
                        <button type="button" @click="openQuestion(q)" x-show="q.status !== 'open'" :disabled="busy"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-1.5 px-3 rounded disabled:opacity-50"
                                x-text="q.status === 'closed' ? @js(__('admin.control.reopen')) : @js(__('admin.control.release'))"></button>
                        <button type="button" @click="closeQuestion(q)" x-show="q.status === 'open'" :disabled="busy"
                                class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold py-1.5 px-3 rounded disabled:opacity-50">{{ __('admin.control.close') }}</button>
                    </div>
                </div>

                {{-- Live tally bars --}}
                <div class="space-y-2">
                    <template x-for="(choice, idx) in q.choices" :key="choice">
                        <div>
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
                            <div class="h-5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :style="'width: ' + pct(q, choice) + '%; background-color: ' + @js($colors)[idx % 4]"></div>
                            </div>
                        </div>
                    </template>
                    <p class="text-right text-xs text-gray-400 mt-1" x-text="@js(__('admin.control.total_prefix')) + ' ' + total(q) + ' ' + @js(__('admin.control.votes_suffix'))"></p>
                    <p x-show="selectedParticipantId !== null && pickFor(q) === null" style="display:none;"
                       class="text-right text-xs text-gray-400">
                        <span x-text="selectedName()"></span> {{ __('admin.control.did_not_vote') }}
                    </p>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
