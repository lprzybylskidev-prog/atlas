@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $backgroundColor = match ($color) {
        'green', 'success' => '#15803d',
        'red', 'error' => '#b91c1c',
        default => '#0f766e',
    };
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" target="_blank" rel="noopener" style="background-color: {{ $backgroundColor }}; border-bottom: 11px solid {{ $backgroundColor }}; border-left: 20px solid {{ $backgroundColor }}; border-right: 20px solid {{ $backgroundColor }}; border-top: 11px solid {{ $backgroundColor }}; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; text-decoration: none;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
