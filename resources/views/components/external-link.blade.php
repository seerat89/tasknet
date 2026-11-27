{{-- A link to another site: opens in a new tab and says so to screen readers. --}}
{{-- PHP drops the newline after a closing tag, so no space appears before punctuation that follows the link. --}}
<a target="_blank" rel="noopener noreferrer" {{ $attributes }}>{{ $slot }}<span class="sr-only"> (opens in a new tab)</span></a><?php ?>
