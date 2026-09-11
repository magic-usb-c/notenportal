@php
    $npLogoUrl = \App\Support\Betriebslogo::url();
    $npLogoAlt = \App\Support\Einstellungen::get(\App\Support\Einstellungen::BETRIEB_NAME) ?: config('app.name');
@endphp
@if($npLogoUrl)
    <img src="{{ $npLogoUrl }}" alt="{{ $npLogoAlt }}" {{ $attributes->merge(['class' => 'w-auto']) }}>
@else
    <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" {{ $attributes->merge(['class' => 'text-accent']) }}>
        <rect x="1" y="1" width="30" height="30" rx="9" fill="currentColor" opacity="0.14"/>
        <rect x="1.5" y="1.5" width="29" height="29" rx="8.5" fill="none" stroke="currentColor" stroke-opacity="0.35"/>
        <path d="M8 22.5l5-6 4 3.5 7-9" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="24" cy="11" r="2.4" fill="currentColor"/>
    </svg>
@endif
