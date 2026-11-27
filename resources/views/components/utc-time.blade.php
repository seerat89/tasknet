@props(['value'])

<time datetime="{{ $value->toIso8601String() }}">{{ $value->copy()->utc()->format('F j, Y \a\t H:i') }} UTC</time>
