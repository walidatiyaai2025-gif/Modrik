@php
    $host = strtolower(request()->getHost());
    $environment = str_starts_with($host, 'demo.')
        ? 'DEMO'
        : strtoupper((string) config('app.env', 'UNKNOWN'));
    $currentLocale = app()->getLocale();
@endphp

<div class="modrik-topbar-context" data-testid="modrik-admin-topbar-context">
    <div class="modrik-environment-badge" title="{{ request()->getHost() }}">
        <span class="modrik-environment-dot" aria-hidden="true"></span>
        <span>{{ $environment }}</span>
    </div>

    <nav class="modrik-locale-switcher" aria-label="Admin language">
        @foreach (['ar' => 'AR', 'en' => 'EN', 'fr' => 'FR'] as $locale => $label)
            <a
                class="modrik-locale-link"
                href="{{ request()->fullUrlWithQuery(['admin_locale' => $locale]) }}"
                hreflang="{{ $locale }}"
                @if ($currentLocale === $locale) aria-current="true" @endif
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
