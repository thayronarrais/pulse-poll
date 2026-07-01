@extends('layouts.admin')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.live.title') }}</h1>
    <a href="{{ route('admin.live-sessions.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-sm transition text-center">
        + {{ __('admin.live.create') }}
    </a>
</div>

@php
    $badges = [
        'draft' => ['bg-gray-100 text-gray-700', __('admin.live.status_draft')],
        'live' => ['bg-green-100 text-green-700', __('admin.live.status_live')],
        'finished' => ['bg-red-100 text-red-700', __('admin.live.status_finished')],
    ];
@endphp

<div class="space-y-3">
    @forelse($sessions as $session)
        @php [$badgeClass, $badgeLabel] = $badges[$session->status] ?? ['bg-gray-100 text-gray-700', $session->status]; @endphp
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $session->title }}</h2>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">{{ $session->questions_count }} {{ __('admin.live.questions_suffix') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.live-sessions.control', $session) }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 px-3 rounded-lg shadow-sm">{{ __('admin.live.panel') }}</a>
                    <a href="{{ route('live.show', $session) }}" target="_blank" class="text-gray-700 hover:text-gray-900 text-sm border border-gray-300 rounded-lg px-3 py-2">🔗 {{ __('messages.link') }}</a>
                    @if($session->status === 'draft')
                        <a href="{{ route('admin.live-sessions.edit', $session) }}" class="text-blue-600 hover:text-blue-800 text-sm border border-blue-200 rounded-lg px-3 py-2">{{ __('admin.live.edit') }}</a>
                    @endif
                    <form action="{{ route('admin.live-sessions.destroy', $session) }}" method="POST" onsubmit="return confirm('{{ __('admin.live.confirm_delete') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm border border-red-200 rounded-lg px-3 py-2">{{ __('admin.live.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 px-6 py-12 text-center text-gray-500">
            {{ __('admin.live.empty') }} <a href="{{ route('admin.live-sessions.create') }}" class="text-indigo-600 hover:underline">{{ __('admin.live.empty_cta') }}</a>
        </div>
    @endforelse
</div>
@endsection
