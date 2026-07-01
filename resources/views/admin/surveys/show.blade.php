@extends('layouts.admin')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.survey_show.title') }}</h1>
    <a href="{{ route('admin.surveys.index') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ __('admin.survey_form.back_to_list') }}</a>
</div>

{{-- Public link with QR code, same pattern as the live control panel --}}
<div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 mb-6" x-data="shareLink(@js(route('surveys.wizard', $survey)))" x-init="makeQr()">
    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
        <img x-show="qrSrc" :src="qrSrc" alt="{{ __('admin.surveys.qr_alt') }}" width="160" height="160"
             class="w-40 h-40 rounded-lg border border-gray-200 bg-white p-2 shadow-sm shrink-0"
             style="image-rendering: pixelated; display: none;">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-700 mb-1">{{ __('admin.surveys.public_link') }}</p>
            <a href="{{ route('surveys.wizard', $survey) }}" target="_blank"
               class="text-sm text-indigo-600 hover:underline break-all">{{ route('surveys.wizard', $survey) }}</a>
            <div class="mt-2">
                <button type="button" @click="copyLink()"
                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg shadow-sm transition">
                    <span x-show="!linkCopied">📋 {{ __('admin.surveys.copy_link') }}</span>
                    <span x-show="linkCopied" style="display:none;">✓ {{ __('admin.surveys.copied') }}</span>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ __('admin.surveys.qr_hint') }}</p>
        </div>
    </div>
</div>

<div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-900">{{ $survey->title }}</h2>
        @if($survey->description)
            <p class="mt-2 text-gray-600">{{ $survey->description }}</p>
        @endif
        <div class="mt-4 text-sm text-gray-500">
            {{ __('admin.survey_show.created_at') }} {{ $survey->created_at->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="p-6 bg-gray-50">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">{{ __('admin.survey_show.questions_count') }} ({{ $survey->questions->count() }})</h3>
        
        <div class="space-y-6">
            @foreach($survey->questions as $index => $question)
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="text-lg font-medium text-gray-900">
                            {{ $index + 1 }}. {{ $question->text }}
                        </h4>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if($question->type === 'single') bg-blue-100 text-blue-800 
                            @elseif($question->type === 'multiple') bg-green-100 text-green-800 
                            @else bg-purple-100 text-purple-800 @endif">
                            @if($question->type === 'single')
                                {{ __('admin.survey_show.type_single') }}
                            @elseif($question->type === 'multiple')
                                {{ __('admin.survey_show.type_multiple') }}
                            @else
                                {{ __('admin.survey_show.type_limited') }} ({{ $question->limit }})
                            @endif
                        </span>
                    </div>

                    <div class="mt-3 pl-4 border-l-2 border-indigo-100">
                        @if($question->image_path)
                            <div class="mb-4">
                                <img src="{{ Str::startsWith($question->image_path, 'http') ? $question->image_path : asset('storage/' . $question->image_path) }}" class="max-h-40 w-auto rounded border border-gray-200">
                            </div>
                        @endif
                        <ul class="space-y-2">
                            @foreach($question->options as $option)
                                <li class="flex items-center text-gray-700">
                                    @if($question->type === 'single')
                                        <div class="h-4 w-4 rounded-full border border-gray-300 mr-2"></div>
                                    @else
                                        <div class="h-4 w-4 rounded border border-gray-300 mr-2"></div>
                                    @endif
                                    {{ $option->text }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
