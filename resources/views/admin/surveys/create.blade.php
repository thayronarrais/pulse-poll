@extends('layouts.admin')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.survey_form.create_title') }}</h1>
    <a href="{{ route('admin.surveys.index') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ __('admin.survey_form.back_to_list') }}</a>
</div>

<form action="{{ route('admin.surveys.store') }}" method="POST" class="space-y-8" x-data="surveyForm()" @keydown.enter="$event.target.tagName !== 'TEXTAREA' ? $event.preventDefault() : null" enctype="multipart/form-data">
    @csrf

    <!-- Survey Details -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">{{ __('admin.survey_form.details') }}</h2>
        <div class="space-y-4">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.field_title') }}</label>
                <input type="text" name="title" id="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border" required placeholder="{{ __('admin.survey_form.title_placeholder') }}">
            </div>

            <div>
                <label for="theme_color" class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.theme_color') }}</label>
                <div class="flex items-center mt-1">
                    <input type="color" name="theme_color" id="theme_color" value="#4f46e5" class="h-10 w-14 rounded cursor-pointer border-0 p-0 mr-3">
                    <span class="text-sm text-gray-500">{{ __('admin.survey_form.theme_color_hint') }}</span>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.description_optional') }}</label>
                <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border" placeholder="{{ __('admin.survey_form.description_placeholder') }}"></textarea>
            </div>
        </div>
    </div>

    <!-- Questions -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-4 border-b pb-2">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('admin.survey_form.questions') }}</h2>
            <button type="button" @click="addQuestion()" class="text-sm bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-semibold py-1.5 px-3 rounded border border-indigo-200 transition">
                + {{ __('admin.survey_form.add_question') }}
            </button>
        </div>

        <div class="space-y-6">
            <template x-for="(question, qIndex) in questions" :key="question.id">
                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 relative">
                    <div class="absolute top-4 right-4 flex gap-3 items-center">
                        <button type="button" @click="moveQuestionUp(qIndex)" x-show="qIndex > 0" class="text-gray-500 hover:text-indigo-600 text-lg p-1 bg-white rounded shadow-sm border border-gray-200" title="{{ __('admin.survey_form.move_up') }}">
                            ↑
                        </button>
                        <button type="button" @click="moveQuestionDown(qIndex)" x-show="qIndex < questions.length - 1" class="text-gray-500 hover:text-indigo-600 text-lg p-1 bg-white rounded shadow-sm border border-gray-200" title="{{ __('admin.survey_form.move_down') }}">
                            ↓
                        </button>
                        <button type="button" @click="removeQuestion(qIndex)" class="text-red-500 hover:text-red-700 text-sm font-medium" x-show="questions.length > 1">
                            {{ __('admin.survey_form.remove') }}
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 mt-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.question_text') }} <span x-text="qIndex + 1"></span></label>
                            <input type="text" x-model="question.text" :name="`questions[${qIndex}][text]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border" required>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.question_image_optional') }}</label>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center mt-1 gap-2">
                                <input type="file" :name="`questions[${qIndex}][image]`" accept="image/*" class="block w-full sm:w-auto flex-grow text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-md">
                                <span class="text-sm font-bold text-gray-400 hidden sm:inline-block">{{ __('messages.or') }}</span>
                                <button type="button" @click="openGiphyModal(qIndex)" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-700 text-sm whitespace-nowrap shadow flex items-center gap-1 w-full sm:w-auto justify-center mt-2 sm:mt-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    {{ __('admin.survey_form.search_giphy') }}
                                </button>
                            </div>

                            <!-- Hidden input for Giphy URL -->
                            <input type="hidden" :name="`questions[${qIndex}][giphy_url]`" x-model="question.giphy_url">
                            
                            <div x-show="question.giphy_url" style="display: none;" class="mt-3 relative inline-block">
                                <span class="block text-xs font-semibold text-gray-500 mb-1">{{ __('admin.survey_form.selected_gif') }}</span>
                                <img :src="question.giphy_url" class="h-32 w-auto rounded border shadow-sm">
                                <button type="button" @click="question.giphy_url = null" class="absolute top-5 -right-2 bg-red-500 text-white rounded-full p-1 shadow-md hover:bg-red-600 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.question_type') }}</label>
                            <select x-model="question.type" :name="`questions[${qIndex}][type]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border bg-white">
                                <option value="single">{{ __('admin.survey_form.type_single') }}</option>
                                <option value="multiple">{{ __('admin.survey_form.type_multiple') }}</option>
                                <option value="limited">{{ __('admin.survey_form.type_limited') }}</option>
                            </select>
                        </div>

                        <div x-show="question.type === 'limited'" class="transition">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.survey_form.allowed_limit') }}</label>
                            <input type="number" x-model="question.limit" :name="`questions[${qIndex}][limit]`" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border" :required="question.type === 'limited'">
                        </div>
                    </div>

                    <!-- Options for this question -->
                    <div class="mt-4 pl-4 border-l-2 border-indigo-200">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.survey_form.options') }}</h3>
                            <button type="button" @click="addOption(qIndex)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                + {{ __('admin.survey_form.add_option') }}
                            </button>
                        </div>
                        
                        <div class="space-y-2">
                            <template x-for="(option, oIndex) in question.options" :key="option.id">
                                <div class="flex items-center gap-2">
                                    <div class="flex-grow">
                                        <input type="text" x-model="option.text" :name="`questions[${qIndex}][options][${oIndex}][text]`" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border" placeholder="{{ __('admin.survey_form.option_placeholder') }}" required>
                                    </div>
                                    <button type="button" @click="removeOption(qIndex, oIndex)" class="text-red-500 hover:text-red-700 p-2" x-show="question.options.length > 1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </template>
            <div x-show="questions.length === 0" class="text-center py-4 text-gray-500 text-sm">
                {{ __('admin.survey_form.no_questions_added') }}
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-150 ease-in-out text-lg">
            {{ __('admin.survey_form.save_survey') }}
        </button>
    </div>

    <!-- Giphy Modal -->
    <div x-show="showGiphyModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showGiphyModal" x-transition.opacity class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="showGiphyModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showGiphyModal" x-transition.scale class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2" id="modal-title">
                                <span class="bg-black text-white px-2 py-1 rounded text-sm uppercase tracking-wider">Giphy</span>
                                {{ __('admin.survey_form.giphy_search_title') }}
                            </h3>
                            <div class="mt-4 flex gap-2">
                                <input type="text" x-model="giphyQuery" @keydown.enter.prevent="searchGiphy('search')" class="flex-grow shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-md border-gray-300 rounded-md p-3 border" placeholder="{{ __('admin.survey_form.giphy_placeholder') }}">
                                <button type="button" @click="searchGiphy('search')" class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-6 py-3 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none">{{ __('messages.search') }}</button>
                            </div>
                            
                            <div class="mt-6 h-96 overflow-y-auto p-1">
                                <div class="columns-2 sm:columns-3 gap-3 space-y-3">
                                    <div x-show="isSearchingGiphy" class="col-span-full text-center py-10 text-gray-500 flex flex-col items-center break-inside-avoid w-full">
                                        <svg class="animate-spin h-8 w-8 text-indigo-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        {{ __('admin.survey_form.giphy_searching') }}
                                    </div>
                                    <template x-for="gif in giphyResults" :key="gif.id">
                                        <div class="cursor-pointer hover:opacity-80 transition transform hover:scale-105 border-2 border-transparent hover:border-indigo-500 rounded bg-gray-100 relative group overflow-hidden break-inside-avoid" @click="selectGif(gif.images.original.url)">
                                            <img :src="gif.images.fixed_width_small.url" class="w-full h-auto object-cover">
                                            <div class="absolute inset-0 bg-indigo-600/20 opacity-0 group-hover:opacity-100 flex items-center justify-center transition">
                                                <span class="bg-indigo-600 text-white text-xs px-2 py-1 rounded shadow">{{ __('admin.survey_form.giphy_select') }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="!isSearchingGiphy && giphyResults.length === 0 && giphyQuery !== ''" class="col-span-full text-center py-10 text-gray-500 break-inside-avoid w-full">{{ __('admin.survey_form.giphy_empty') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                    <button type="button" @click="showGiphyModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">{{ __('messages.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    function surveyForm() {
        return {
            questions: [
                {
                    id: Date.now(),
                    text: '',
                    type: 'single',
                    limit: null,
                    giphy_url: null,
                    options: [
                        { id: Date.now() + 1, text: '' },
                        { id: Date.now() + 2, text: '' }
                    ]
                }
            ],
            // Giphy State
            showGiphyModal: false,
            giphyQuery: '',
            giphyResults: [],
            isSearchingGiphy: false,
            currentQuestionIndexForGiphy: null,
            giphyApiKey: '{{ config("services.giphy.key") }}',

            openGiphyModal(qIndex) {
                this.currentQuestionIndexForGiphy = qIndex;
                this.showGiphyModal = true;
                if(this.giphyResults.length === 0) {
                    this.searchGiphy('trending');
                }
            },

            async searchGiphy(type = 'search') {
                this.isSearchingGiphy = true;
                try {
                    let endpoint = type === 'trending' ? 'trending' : 'search';
                    let queryParam = type === 'search' ? `&q=${encodeURIComponent(this.giphyQuery)}` : '';
                    let response = await fetch(`https://api.giphy.com/v1/gifs/${endpoint}?api_key=${this.giphyApiKey}&limit=21${queryParam}`);
                    let data = await response.json();
                    this.giphyResults = data.data;
                } catch(e) {
                    console.error("Erro ao buscar Giphy", e);
                }
                this.isSearchingGiphy = false;
            },

            selectGif(url) {
                this.questions[this.currentQuestionIndexForGiphy].giphy_url = url;
                this.showGiphyModal = false;
            },

            addQuestion() {
                this.questions.push({
                    id: Date.now(),
                    text: '',
                    type: 'single',
                    limit: null,
                    giphy_url: null,
                    options: [
                        { id: Date.now() + 1, text: '' }
                    ]
                });
            },
            removeQuestion(index) {
                this.questions.splice(index, 1);
            },
            addOption(qIndex) {
                this.questions[qIndex].options.push({
                    id: Date.now(),
                    text: ''
                });
            },
            removeOption(qIndex, oIndex) {
                this.questions[qIndex].options.splice(oIndex, 1);
            },
            moveQuestionUp(index) {
                if (index > 0) {
                    const temp = this.questions[index - 1];
                    this.questions[index - 1] = this.questions[index];
                    this.questions[index] = temp;
                }
            },
            moveQuestionDown(index) {
                if (index < this.questions.length - 1) {
                    const temp = this.questions[index + 1];
                    this.questions[index + 1] = this.questions[index];
                    this.questions[index] = temp;
                }
            }
        }
    }
</script>
@endsection
