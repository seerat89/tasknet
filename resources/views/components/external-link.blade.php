{{-- A link to another site: opens in a new tab and says so to screen readers. --}}
<a target="_blank" rel="noopener noreferrer" {{ $attributes }}>{{ $slot }}<span class="sr-only"> (opens in a new tab)</span></a>
