@if (isset($subscriptionBanner))
    <div @class([
        'px-4 py-2 text-center text-sm font-medium text-white',
        'bg-warning-600' => ($subscriptionBanner['type'] ?? '') === 'grace',
        'bg-danger-600' => in_array($subscriptionBanner['type'] ?? '', ['suspended', 'read_only']),
    ])>
        {{ $subscriptionBanner['message'] ?? 'Subscription notice' }}
        @if (! empty($subscriptionBanner['expires_at']))
            — Expires {{ \Illuminate\Support\Carbon::parse($subscriptionBanner['expires_at'])->diffForHumans() }}
        @endif
    </div>
@endif
