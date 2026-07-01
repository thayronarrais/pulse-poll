@extends('layouts.admin')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.live_form.edit_title') }}</h1>
    <a href="{{ route('admin.live-sessions.index') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ __('messages.back') }}</a>
</div>

<form action="{{ route('admin.live-sessions.update', $liveSession) }}" method="POST" class="space-y-6 max-w-2xl">
    @csrf
    @method('PUT')

    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">{{ __('admin.live_form.details') }}</h2>
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">{{ __('admin.live_form.field_title') }}</label>
            <input type="text" name="title" id="title" required value="{{ old('title', $liveSession->title) }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-md text-lg">
            {{ __('admin.live_form.update') }}
        </button>
    </div>
</form>
@endsection
