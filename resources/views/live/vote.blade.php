@extends('layouts.live')

@section('title', $liveSession->title)

@php
    $config = [
        'slug' => $liveSession->slug,
        'voteUrl' => route('live.vote', $liveSession),
        'identifyUrl' => route('live.identify', $liveSession),
        'csrf' => csrf_token(),
        'state' => $state,
        'voterName' => $voterName,
    ];
    $colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981'];
@endphp

@section('content')
<div x-data="liveVoter(@js($config))" class="min-h-screen flex flex-col">
    <header class="px-5 py-4 border-b border-white/10 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-white/90 truncate">{{ $liveSession->title }}</h1>
        <x-locale-switcher :dark="true" class="flex-shrink-0" />
    </header>

    <div class="flex-grow flex flex-col justify-center px-5 py-6">
        {{-- Welcome / optional identification (asked once on entry) --}}
        <template x-if="screen === 'intro'">
            <div class="text-center">
                <p class="text-xl font-medium text-white/90 mb-6">{{ __('live.welcome') }}</p>

                {{-- Optional photo: tap to upload or take a photo --}}
                <div class="flex flex-col items-center mb-6">
                    <label for="voter-photo" class="relative cursor-pointer">
                        <div class="w-28 h-28 rounded-full bg-white/10 border-2 border-dashed border-white/30 flex items-center justify-center overflow-hidden">
                            <template x-if="photoPreview">
                                <img :src="photoPreview" alt="{{ __('live.your_photo_alt') }}" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!photoPreview">
                                <svg class="w-10 h-10 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </template>
                        </div>
                        <span class="absolute bottom-0 right-0 bg-white text-gray-900 rounded-full w-9 h-9 flex items-center justify-center shadow text-lg">📷</span>
                        <input type="file" id="voter-photo" accept="image/*" capture="user" class="hidden" @change="setPhoto($event)">
                    </label>
                    <p class="text-xs text-white/50 mt-2">{{ __('live.add_photo') }}</p>
                </div>

                <div class="text-left mb-8">
                    <label for="voter-name" class="block text-sm font-medium text-white/70 mb-1">{{ __('live.your_name_optional') }}</label>
                    <input
                        type="text"
                        id="voter-name"
                        x-model="voterName"
                        maxlength="255"
                        placeholder="{{ __('live.name_placeholder') }}"
                        @keydown.enter="enter()"
                        class="w-full rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-white/40">
                </div>
                <button
                    type="button"
                    @click="enter()"
                    :disabled="submitting"
                    class="w-full rounded-2xl bg-white text-gray-900 font-extrabold text-xl py-4 shadow-lg active:scale-95 transition disabled:opacity-50">
                    {{ __('live.enter') }}
                </button>
            </div>
        </template>

        {{-- Waiting for a question to open --}}
        <template x-if="screen === 'waiting'">
            <div class="text-center text-white/70">
                <div class="text-6xl mb-4">⏳</div>
                <p class="text-xl font-medium">{{ __('live.waiting') }}</p>
            </div>
        </template>

        {{-- Session finished --}}
        <template x-if="screen === 'finished'">
            <div class="text-center text-white/70">
                <div class="text-6xl mb-4">🎉</div>
                <p class="text-xl font-medium">{{ __('live.finished') }}</p>
            </div>
        </template>

        {{-- Question open: big vote buttons --}}
        <template x-if="screen === 'open'">
            <div class="flex flex-col gap-8 pt-8">
                <template x-if="question.text">
                    <h2 class="text-center font-extrabold text-white px-2 leading-tight break-words"
                        :class="questionTextSize"
                        x-text="question.text"></h2>
                </template>
                <template x-for="(choice, idx) in question.choices" :key="choice">
                    <div class="relative">
                        <button
                            type="button"
                            @click="vote(choice)"
                            :disabled="submitting"
                            class="w-full rounded-2xl font-extrabold text-white shadow-lg active:scale-95 transition disabled:opacity-50 flex items-center justify-center"
                            :class="[
                                question.choices.length > 2 ? 'text-4xl py-10' : 'text-5xl py-14',
                                masterPicks[choice] && masterPicks[choice].length ? 'ring-4 ring-amber-300' : ''
                            ]"
                            :style="'background-color: ' + @js($colors)[idx % 4]"
                            x-text="choice">
                        </button>

                        {{-- Master suggestion: prominent banner with photo + name over the chosen option --}}
                        <template x-if="masterPicks[choice] && masterPicks[choice].length">
                            <div class="absolute -top-6 left-1/2 -translate-x-1/2 z-10 flex flex-wrap justify-center gap-2 w-full px-2">
                                <template x-for="m in masterPicks[choice]" :key="m.name">
                                    <div class="flex items-center gap-2 bg-white text-gray-900 rounded-full pl-1 pr-4 py-1 shadow-xl ring-2 ring-amber-300">
                                        <img
                                            :src="m.photo_url || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(m.name) + '&background=random&color=fff&bold=true&size=96')"
                                            :alt="m.name"
                                            class="w-10 h-10 rounded-full object-cover bg-gray-300">
                                        <span class="text-base font-extrabold whitespace-nowrap leading-tight">
                                            <span class="text-amber-500">👑</span> <span x-text="m.name"></span>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Voted (live tally) or closed (final results) --}}
        <template x-if="screen === 'voted' || screen === 'results'">
            <div>
                <template x-if="question.text">
                    <h2 class="text-center font-extrabold text-white px-2 leading-tight break-words mb-6"
                        :class="questionTextSize"
                        x-text="question.text"></h2>
                </template>
                <p class="text-center mb-6 text-white/80 font-medium"
                   x-text="screen === 'results' ? @js(__('live.final_result')) : @js(__('live.vote_registered'))"></p>
                <div class="space-y-4">
                    <template x-for="(choice, idx) in question.choices" :key="choice">
                        <div>
                            <div class="flex justify-between mb-1 text-sm font-semibold">
                                <span x-text="choice + (myChoice === choice ? ' (' + @js(__('live.your_vote')) + ')' : '')"></span>
                                <span x-text="pct(choice) + '% (' + (tally?.[choice] ?? 0) + ')'"></span>
                            </div>
                            <div class="h-6 rounded-full bg-white/10 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :style="'width: ' + pct(choice) + '%; background-color: ' + @js($colors)[idx % 4]"></div>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="text-center mt-6 text-white/50 text-sm" x-text="@js(__('live.total_votes')) + ' ' + total"></p>
            </div>
        </template>
    </div>
</div>
@endsection
