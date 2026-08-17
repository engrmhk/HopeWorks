@php
    $appName = app(\App\Services\Themes\ThemeResolver::class)->resolvedConfig()['identity']['app_name'] ?? 'Hope Works';
@endphp

<span class="hw-brand-mark" title="{{ $appName }}" aria-label="{{ $appName }}">
    <svg viewBox="0 0 32 32" width="28" height="28" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="currentColor" opacity="0.12"/>
        <path d="M16 7.5c.4 0 .75.22.94.57l6.3 11.5c.38.7-.12 1.55-.94 1.55H9.7c-.82 0-1.32-.85-.94-1.55l6.3-11.5c.19-.35.54-.57.94-.57Z" fill="currentColor"/>
        <circle cx="16" cy="20.2" r="1.6" fill="var(--hw-canvas-bg)"/>
    </svg>
</span>
