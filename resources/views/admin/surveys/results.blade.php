@extends('layouts.admin')

@section('content')
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('admin.results.title') }} {{ $survey->title }}</h1>
        <p class="text-gray-600 mt-1">{{ __('admin.results.total_responses') }} <span class="font-bold text-indigo-600">{{ $totalResponses }}</span> | {{ __('admin.results.identified') }} <span class="font-bold text-green-600">{{ $identifiedResponses }}</span></p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('surveys.wizard', $survey) }}" target="_blank" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded border border-gray-300 shadow-sm transition">
            {{ __('admin.results.view_public') }}
        </a>
        <a href="{{ route('admin.surveys.index') }}" class="text-indigo-600 hover:text-indigo-900 font-medium py-2 px-4">{{ __('messages.back') }}</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-8">
    <form action="{{ route('admin.surveys.results', $survey) }}" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="w-full md:w-1/4">
            <label class="block text-sm font-medium text-gray-700">{{ __('admin.results.start_date') }}</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
        </div>
        <div class="w-full md:w-1/4">
            <label class="block text-sm font-medium text-gray-700">{{ __('admin.results.end_date') }}</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
        </div>
        <div class="w-full md:w-2/4">
            <label class="block text-sm font-medium text-gray-700">{{ __('admin.results.respondent_name') }}</label>
            <input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('admin.results.search_by_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
        </div>
        <div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-6 rounded shadow transition w-full md:w-auto h-10">
                {{ __('messages.filter') }}
            </button>
        </div>
        @if(request()->anyFilled(['start_date', 'end_date', 'name']))
            <div>
                <a href="{{ route('admin.surveys.results', $survey) }}" class="text-gray-500 hover:text-gray-700 text-sm font-medium flex items-center h-10 px-2">{{ __('messages.clear') }}</a>
            </div>
        @endif
    </form>
</div>

<!-- Charts -->
@if($totalResponses > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        @foreach($stats as $questionId => $stat)
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ $stat['question_text'] }}</h3>
                <div class="relative h-64 w-full">
                    <canvas id="chart-{{ $questionId }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Identified Respondents Table -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">{{ __('admin.results.identified_respondents') }}</h3>
            <p class="text-sm text-gray-500">{{ __('admin.results.identified_respondents_hint') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.results.th_name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.results.th_email') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.results.th_date') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.results.th_highlight') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($responses->whereNotNull('name') as $resp)
                        <tr x-data="{ isHighlighted: {{ $resp->is_highlighted ? 'true' : 'false' }} }">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $resp->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $resp->email ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $resp->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                <button type="button" 
                                    @click="
                                        fetch('{{ route('admin.surveys.responses.toggle-highlight', [$survey->id, $resp->id], false) }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                            }
                                        }).then(res => res.json()).then(data => {
                                            if(data.success) {
                                                isHighlighted = data.is_highlighted;
                                            } else {
                                                console.error(data);
                                                alert(@js(__('admin.results.js_update_error')) + (data.message || @js(__('admin.results.js_unknown'))));
                                            }
                                        }).catch(err => {
                                            console.error('Fetch error:', err);
                                            alert(@js(__('admin.results.js_connection_error')));
                                        })
                                    "
                                    :class="isHighlighted ? 'text-yellow-400 hover:text-yellow-500' : 'text-gray-300 hover:text-gray-400'"
                                    class="transition focus:outline-none" title="{{ __('admin.results.highlight_title') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" :fill="isHighlighted ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">{{ __('admin.results.no_identified') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-12 text-center text-gray-500">
        {{ __('admin.results.no_responses') }}
    </div>
@endif

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const stats = @json($stats);
        
        Object.keys(stats).forEach(questionId => {
            const stat = stats[questionId];
            const ctx = document.getElementById('chart-' + questionId);
            
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: stat.labels,
                        datasets: [{
                            data: stat.data,
                            backgroundColor: stat.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }
        });
    });
</script>
@endsection
