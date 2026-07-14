@props(['url'])
<tr>
<td class="header">
<table align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-logo-cell">
<a href="{{ $url }}" target="_blank" rel="noopener">
<img src="{{ url('/brand/atlas-mail-logo.png') }}" width="44" height="44" class="brand-logo" alt="{{ trim(strip_tags((string) $slot)) }}" />
</a>
</td>
<td class="brand-copy-cell">
<a href="{{ $url }}" class="brand-name" target="_blank" rel="noopener">{!! $slot !!}</a>
<div class="brand-subtitle">{{ __('mail.brand_subtitle') }}</div>
</td>
</tr>
</table>
</td>
</tr>
