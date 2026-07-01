<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $survey->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --theme-color: {{ $survey->theme_color ?? '#4f46e5' }};
            --theme-color-dark: color-mix(in srgb, var(--theme-color) 80%, black);
            --theme-color-light: color-mix(in srgb, var(--theme-color) 10%, white);
            --theme-gradient: linear-gradient(135deg, var(--theme-color), var(--theme-color-dark));
        }

        body {
            font-family: 'Outfit', sans-serif;
        }
        .bg-animated-gradient {
            background: linear-gradient(-45deg, var(--theme-color), #9333ea, #ec4899, var(--theme-color-dark));
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Custom Checkbox / Radio Animations */
        .option-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .option-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .option-card.selected {
            border-color: var(--theme-color) !important;
            background-color: var(--theme-color-light) !important;
            transform: scale(1.02);
            box-shadow: 0 10px 25px -5px var(--theme-color-light);
        }
        
        /* Theme Utilities */
        .theme-bg-gradient {
            background: var(--theme-gradient) !important;
        }
        .theme-bg-gradient:hover {
            background: var(--theme-color-dark) !important;
        }
        .theme-text {
            color: var(--theme-color) !important;
        }
        .theme-border {
            border-color: var(--theme-color) !important;
        }
        .theme-bg-solid {
            background-color: var(--theme-color) !important;
        }
    </style>
</head>
<body class="bg-animated-gradient text-gray-900 min-h-screen flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8">

    <div class="absolute top-4 right-4 bg-white/20 backdrop-blur rounded-lg px-1 py-0.5">
        <x-locale-switcher :dark="true" />
    </div>

    <!-- Glassmorphism Card -->
    <div class="w-full max-w-2xl bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl overflow-hidden border border-white/50" x-data="surveyWizard()">
        
        <!-- Progress Bar -->
        <div class="bg-gray-200/50 h-2 w-full">
            <div class="theme-bg-gradient h-2 transition-all duration-500 ease-out" :style="`width: ${progressPercentage}%`"></div>
        </div>

        <div class="p-8 sm:p-12">
            
            <form action="{{ route('surveys.submit', $survey) }}" method="POST" id="survey-form">
                @csrf

                <!-- Step 0: Welcome & Identification -->
                <div x-show="step === 0" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    
                    <div class="text-center mb-10">
                        <h1 class="text-4xl font-extrabold theme-text mb-4">{{ $survey->title }}</h1>
                        @if($survey->description)
                            <p class="text-gray-600 text-lg">{{ $survey->description }}</p>
                        @endif
                    </div>
                    
                    <div class="bg-gray-50 rounded-2xl p-6 mb-8 border border-gray-100">
                        <h2 class="text-xl font-bold mb-2 text-gray-800">{{ __('survey.begin_title') }}</h2>
                        <p class="text-gray-500 mb-6">{{ __('survey.begin_subtitle') }}</p>

                        <div class="space-y-5">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">{{ __('survey.your_name') }}</label>
                                <input type="text" name="name" id="name" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-4 border text-lg transition" placeholder="{{ __('survey.name_placeholder') }}">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">{{ __('survey.your_email') }}</label>
                                <input type="email" name="email" id="email" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-4 border text-lg transition" placeholder="{{ __('survey.email_placeholder') }}">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <button type="button" @click="nextStep()" class="w-full sm:w-auto theme-bg-gradient text-white font-bold py-4 px-10 rounded-2xl shadow-lg transition-all duration-300 text-xl flex items-center justify-center group">
                            {{ __('survey.start') }}
                            <svg class="w-6 h-6 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Questions Steps -->
                @foreach($survey->questions as $index => $question)
                <div x-show="step === {{ $index + 1 }}" style="display: none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
                    
                    <div class="mb-8">
                        <span class="inline-block px-4 py-1.5 rounded-full theme-text theme-bg-light text-sm font-bold mb-4" style="background-color: var(--theme-color-light)">
                            {{ __('survey.question_of', ['current' => $index + 1, 'total' => $survey->questions->count()]) }}
                        </span>
                        <h2 class="text-3xl font-bold text-gray-800 leading-tight">{{ $question->text }}</h2>
                        @if($question->type === 'limited')
                            <p class="theme-text font-semibold mt-3 inline-block px-3 py-1 rounded-lg" style="background-color: var(--theme-color-light)">{{ __('survey.limited_hint', ['limit' => $question->limit]) }}</p>
                        @endif
                    </div>

                    @if($question->image_path)
                        <div class="mb-8 rounded-xl overflow-hidden shadow-sm flex justify-center bg-gray-50 p-2 border border-gray-100">
                            <img src="{{ Str::startsWith($question->image_path, 'http') ? $question->image_path : asset('storage/' . $question->image_path) }}" alt="{{ __('survey.image_alt') }}" class="max-h-64 w-auto rounded-lg object-contain">
                        </div>
                    @endif
                    
                    <div class="space-y-4 mb-10">
                        @if($question->type === 'single')
                            @foreach($question->options as $option)
                                @php
                                    $vipChoosers = $highlightedResponses->filter(function($hr) use ($question, $option) {
                                        return $hr->answers->where('question_id', $question->id)->where('option_id', $option->id)->isNotEmpty();
                                    });
                                @endphp
                                <label class="option-card flex items-center p-5 border-2 rounded-2xl cursor-pointer relative" :class="{'selected': answers[{{ $question->id }}] == {{ $option->id }}, 'border-gray-200 bg-white hover:border-gray-300': answers[{{ $question->id }}] != {{ $option->id }}}">
                                    @if($vipChoosers->isNotEmpty())
                                        <div class="absolute -top-3 -right-3 flex -space-x-2 z-10">
                                            @foreach($vipChoosers as $vip)
                                                <img src="https://ui-avatars.com/api/?name={{ urlencode($vip->name) }}&background=random&color=fff&bold=true&size=64" class="w-8 h-8 rounded-full border-2 border-white shadow-sm" title="{{ __('survey.vip_chose', ['name' => $vip->name]) }}">
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="flex-shrink-0 relative w-6 h-6 flex items-center justify-center mr-4">
                                        <div class="w-6 h-6 rounded-full border-2 transition-colors duration-200" :class="answers[{{ $question->id }}] == {{ $option->id }} ? 'theme-border theme-bg-solid' : 'border-gray-300'"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-white absolute" x-show="answers[{{ $question->id }}] == {{ $option->id }}" x-transition.scale></div>
                                        <!-- Hidden real input -->
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" x-model="answers[{{ $question->id }}]" class="hidden">
                                    </div>
                                    <span class="text-xl font-medium text-gray-800">{{ $option->text }}</span>
                                </label>
                            @endforeach
                        @elseif($question->type === 'multiple' || $question->type === 'limited')
                            @foreach($question->options as $option)
                                @php
                                    $vipChoosers = $highlightedResponses->filter(function($hr) use ($question, $option) {
                                        return $hr->answers->where('question_id', $question->id)->where('option_id', $option->id)->isNotEmpty();
                                    });
                                @endphp
                                <label class="option-card flex items-center p-5 border-2 rounded-2xl cursor-pointer relative" :class="{'selected': answers[{{ $question->id }}] && answers[{{ $question->id }}].includes('{{ $option->id }}'), 'border-gray-200 bg-white hover:border-gray-300': !answers[{{ $question->id }}] || !answers[{{ $question->id }}].includes('{{ $option->id }}')}">
                                    @if($vipChoosers->isNotEmpty())
                                        <div class="absolute -top-3 -right-3 flex -space-x-2 z-10">
                                            @foreach($vipChoosers as $vip)
                                                <img src="https://ui-avatars.com/api/?name={{ urlencode($vip->name) }}&background=random&color=fff&bold=true&size=64" class="w-8 h-8 rounded-full border-2 border-white shadow-sm" title="{{ __('survey.vip_chose', ['name' => $vip->name]) }}">
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="flex-shrink-0 relative w-6 h-6 flex items-center justify-center mr-4">
                                        <div class="w-6 h-6 rounded-md border-2 transition-colors duration-200" :class="answers[{{ $question->id }}] && answers[{{ $question->id }}].includes('{{ $option->id }}') ? 'theme-border theme-bg-solid' : 'border-gray-300'"></div>
                                        <svg class="w-4 h-4 text-white absolute" x-show="answers[{{ $question->id }}] && answers[{{ $question->id }}].includes('{{ $option->id }}')" x-transition.scale fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        <!-- Hidden real input -->
                                        <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option->id }}" x-model="answers[{{ $question->id }}]" class="hidden" @change="checkLimit({{ $question->id }}, {{ $question->type === 'limited' ? $question->limit : 'null' }})">
                                    </div>
                                    <span class="text-xl font-medium text-gray-800">{{ $option->text }}</span>
                                </label>
                            @endforeach
                            <div x-show="limitError[{{ $question->id }}]" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-semibold mt-3 flex items-center" style="display: none;" x-transition>
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                {{ __('survey.limit_reached') }}
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-between gap-4 mt-8 pt-6 border-t border-gray-100">
                        <button type="button" @click="prevStep()" class="w-1/3 bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 font-bold py-4 px-4 rounded-xl transition text-lg shadow-sm">
                            {{ __('survey.back') }}
                        </button>
                        
                        @if($index === $survey->questions->count() - 1)
                            <button type="button" @click="submitForm()" class="w-2/3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-4 px-4 rounded-xl shadow-lg transition text-lg flex justify-center items-center group" :disabled="!isCurrentStepValid()" :class="{'opacity-50 cursor-not-allowed': !isCurrentStepValid()}">
                                {{ __('survey.finish') }}
                                <svg class="w-6 h-6 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </button>
                        @else
                            <button type="button" @click="nextStep()" class="w-2/3 theme-bg-gradient text-white font-bold py-4 px-4 rounded-xl shadow-lg transition text-lg flex justify-center items-center group" :disabled="!isCurrentStepValid()" :class="{'opacity-50 cursor-not-allowed': !isCurrentStepValid()}">
                                {{ __('survey.next') }}
                                <svg class="w-6 h-6 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>
                        @endif
                    </div>
                </div>
                @endforeach
            </form>
        </div>
    </div>

    <!-- Initialization to show the first step without flicker -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('surveyWizard', () => {
                const totalSteps = {{ $survey->questions->count() }};
                const questionsData = @json($survey->questions);
                
                let initialAnswers = {};
                questionsData.forEach(q => {
                    if (q.type === 'single') {
                        initialAnswers[q.id] = null;
                    } else {
                        initialAnswers[q.id] = [];
                    }
                });

                return {
                    step: 0,
                    totalSteps: totalSteps,
                    answers: initialAnswers,
                    limitError: {},
                    
                    init() {
                        // show the first step immediately upon init
                        this.$nextTick(() => {
                            if(this.step === null) this.step = 0;
                        });
                    },

                    get progressPercentage() {
                        return (this.step / this.totalSteps) * 100;
                    },
                    
                    nextStep() {
                        if (this.step < this.totalSteps) {
                            this.step++;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    },
                    
                    prevStep() {
                        if (this.step > 0) {
                            this.step--;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    },
                    
                    checkLimit(questionId, limit) {
                        if (limit && this.answers[questionId].length > limit) {
                            this.answers[questionId].pop();
                            this.limitError[questionId] = true;
                            setTimeout(() => { this.limitError[questionId] = false; }, 3000);
                        }
                    },
                    
                    isCurrentStepValid() {
                        if (this.step === 0) return true;
                        
                        const currentQuestion = questionsData[this.step - 1];
                        const answer = this.answers[currentQuestion.id];
                        
                        if (currentQuestion.type === 'single') {
                            return answer !== null && answer !== '';
                        } else {
                            return Array.isArray(answer) && answer.length > 0;
                        }
                    },
                    
                    submitForm() {
                        if (this.isCurrentStepValid()) {
                            document.getElementById('survey-form').submit();
                        }
                    }
                }
            })
        })
    </script>
</body>
</html>
