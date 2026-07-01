@extends('layouts.admin')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.surveys.title') }}</h1>
    <a href="{{ route('admin.surveys.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-sm transition text-center">
        + {{ __('admin.surveys.create') }}
    </a>
</div>

<div class="space-y-3">
    @forelse($surveys as $survey)
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4" x-data="shareLink(@js(route('surveys.wizard', $survey)))">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $survey->title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $survey->questions_count }} {{ __('admin.surveys.questions_suffix') }}
                        <span class="text-gray-300 mx-1">&middot;</span>
                        {{ $survey->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.surveys.results', $survey) }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 px-3 rounded-lg shadow-sm">{{ __('admin.surveys.results') }}</a>
                    <a href="{{ route('surveys.wizard', $survey) }}" target="_blank" class="text-gray-700 hover:text-gray-900 text-sm border border-gray-300 rounded-lg px-3 py-2">🔗 {{ __('admin.surveys.public_link') }}</a>
                    <button type="button" @click="copyLink()" class="text-gray-700 hover:text-gray-900 text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <span x-show="!linkCopied">📋 {{ __('admin.surveys.copy_link') }}</span>
                        <span x-show="linkCopied" style="display:none;" class="text-green-600 font-semibold">✓ {{ __('admin.surveys.copied') }}</span>
                    </button>
                    <a href="{{ route('admin.surveys.show', $survey) }}" class="text-indigo-600 hover:text-indigo-800 text-sm border border-indigo-200 rounded-lg px-3 py-2">{{ __('admin.surveys.view') }}</a>
                    <a href="{{ route('admin.surveys.edit', $survey) }}" class="text-blue-600 hover:text-blue-800 text-sm border border-blue-200 rounded-lg px-3 py-2">{{ __('admin.surveys.edit') }}</a>
                    <form action="{{ route('admin.surveys.destroy', $survey) }}" method="POST" onsubmit="return confirm('{{ __('admin.surveys.confirm_delete') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm border border-red-200 rounded-lg px-3 py-2">{{ __('admin.surveys.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 px-6 py-12 text-center text-gray-500">
            {{ __('admin.surveys.empty') }} <a href="{{ route('admin.surveys.create') }}" class="text-indigo-600 hover:underline">{{ __('admin.surveys.empty_cta') }}</a>
        </div>
    @endforelse
</div>
@endsection
