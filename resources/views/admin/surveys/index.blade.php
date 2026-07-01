@extends('layouts.admin')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.surveys.title') }}</h1>
    <a href="{{ route('admin.surveys.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-sm transition duration-150 ease-in-out">
        + {{ __('admin.surveys.create') }}
    </a>
</div>

<div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.surveys.th_title') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.surveys.th_questions') }}</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.surveys.th_created') }}</th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.surveys.th_actions') }}</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($surveys as $survey)
                <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $survey->id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $survey->title }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $survey->questions_count }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $survey->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('surveys.wizard', $survey) }}" target="_blank" class="text-gray-600 hover:text-gray-900 mr-3 border border-gray-300 rounded px-2 py-1 bg-white hover:bg-gray-50 text-xs">🔗 {{ __('admin.surveys.public_link') }}</a>
                        <a href="{{ route('admin.surveys.results', $survey) }}" class="text-green-600 hover:text-green-900 mr-3 font-bold">{{ __('admin.surveys.results') }}</a>
                        <a href="{{ route('admin.surveys.show', $survey) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">{{ __('admin.surveys.view') }}</a>
                        <a href="{{ route('admin.surveys.edit', $survey) }}" class="text-blue-600 hover:text-blue-900 mr-3">{{ __('admin.surveys.edit') }}</a>
                        <form action="{{ route('admin.surveys.destroy', $survey) }}" method="POST" class="inline-block" onsubmit="return confirm('{{ __('admin.surveys.confirm_delete') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('admin.surveys.delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">{{ __('admin.surveys.empty') }} <a href="{{ route('admin.surveys.create') }}" class="text-indigo-600 hover:underline">{{ __('admin.surveys.empty_cta') }}</a></td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
