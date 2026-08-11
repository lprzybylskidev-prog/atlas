<x-mail::message>
@foreach ($sections as $section)
# {{ $section['heading'] }}

@foreach ($section['bodyLines'] as $line)
{{ $line }}

@endforeach
@if ($section['actionLabel'] !== null && $section['actionUrl'] !== null)
<x-mail::button :url="$section['actionUrl']">
{{ $section['actionLabel'] }}
</x-mail::button>
@endif
@if (! $loop->last)
---
@endif
@endforeach
</x-mail::message>
