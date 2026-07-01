@props(['dark' => false])

@php
    $locales = ['en' => 'EN', 'pt' => 'PT'];
    $current = app()->getLocale();
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs font-semibold']) }}>
    @foreach ($locales as $code => $label)
        <a href="{{ route('locale.switch', $code) }}"
           class="px-2 py-1 rounded transition
                  @if ($current === $code)
                      {{ $dark ? 'bg-white/20 text-white' : 'bg-indigo-600 text-white' }}
                  @else
                      {{ $dark ? 'text-white/60 hover:text-white' : 'text-gray-500 hover:text-gray-800' }}
                  @endif">
            {{ $label }}
        </a>
    @endforeach
</div>
