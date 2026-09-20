@php
    use App\Services\ChurchInstanceAccessService;
    use App\Support\LicenseJwtSecret;

    /** @var \App\Models\Church|null $church */

    $revealedApiKey = $revealedApiKey ?? null;
    $hasKey = $church && app(ChurchInstanceAccessService::class)->hasApiKey($church);
    $jwtReady = LicenseJwtSecret::isReady();
    $jwtSecret = LicenseJwtSecret::configured();
    $controlPlaneUrl = rtrim((string) config('app.url'), '/');
    $churchId = $church?->id;
@endphp

<div class="space-y-4" x-data>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Paste these into the church app under <strong>Settings → System → Control Plane connection</strong>, then click Sync Now.
    </p>

    @unless ($jwtReady)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-950/40 dark:text-danger-200">
            LICENSE_JWT_SECRET is missing or shorter than 32 characters on this Control Plane.
            Generate it on the church System page (or with <code>openssl rand -base64 32</code>), then put the same value in Control Plane <code>.env</code> and run <code>php artisan config:clear</code>.
        </div>
    @endunless

    @if (filled($revealedApiKey))
        <div class="rounded-xl border-2 border-warning-400 bg-warning-50 p-4 dark:border-warning-500/50 dark:bg-warning-950/30">
            <p class="text-sm font-semibold text-warning-900 dark:text-warning-100">Instance API key — copy now</p>
            <p class="mt-1 text-xs text-warning-800 dark:text-warning-200">
                This is the only time it is shown. Paste it into the church <strong>Instance API key</strong> field. It is not the JWT secret.
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input
                    type="text"
                    readonly
                    value="{{ $revealedApiKey }}"
                    class="w-full rounded-lg border-warning-300 bg-white px-3 py-2 font-mono text-sm text-gray-950 dark:bg-gray-900 dark:text-white"
                    x-ref="revealedKey"
                >
                <button
                    type="button"
                    class="fi-btn fi-size-md fi-btn-color-warning inline-flex items-center justify-center rounded-lg bg-warning-500 px-3 py-2 text-sm font-semibold text-white"
                    x-on:click="navigator.clipboard.writeText($refs.revealedKey.value)"
                >
                    Copy key
                </button>
            </div>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Control Plane URL</label>
            <div class="mt-1 flex gap-2">
                <input type="text" readonly value="{{ $controlPlaneUrl }}" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" x-ref="cpUrl">
                <button type="button" class="rounded-lg px-3 text-sm font-medium text-primary-600" x-on:click="navigator.clipboard.writeText($refs.cpUrl.value)">Copy</button>
            </div>
        </div>
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Control Plane church ID</label>
            <div class="mt-1 flex gap-2">
                <input type="text" readonly value="{{ $churchId }}" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" x-ref="churchId">
                <button type="button" class="rounded-lg px-3 text-sm font-medium text-primary-600" x-on:click="navigator.clipboard.writeText($refs.churchId.value)">Copy</button>
            </div>
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">License JWT secret</label>
            <div class="mt-1 flex gap-2">
                <input type="text" readonly value="{{ $jwtSecret }}" placeholder="Not set on Control Plane .env" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" x-ref="jwtSecret">
                <button type="button" class="rounded-lg px-3 text-sm font-medium text-primary-600" x-on:click="navigator.clipboard.writeText($refs.jwtSecret.value)">Copy</button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Shared with every church. Must be 32+ characters. Same value as church <strong>License JWT secret</strong>.
            </p>
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Instance API key status</label>
            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                @if (filled($revealedApiKey))
                    Shown above — copy it into the church app now.
                @elseif ($hasKey)
                    A key is saved (hash only). Generate/rotate below if you no longer have the plain key.
                @else
                    No key yet — click Generate API key.
                @endif
            </p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @unless ($hasKey)
            <x-filament::button color="success" icon="heroicon-m-key" wire:click="generateChurchApiKey">
                Generate API key
            </x-filament::button>
        @else
            <x-filament::button color="warning" icon="heroicon-m-arrow-path" wire:click="rotateChurchApiKey" wire:confirm="Rotate this church’s API key? The old key stops working immediately.">
                Rotate API key
            </x-filament::button>
            <x-filament::button color="danger" outlined icon="heroicon-o-no-symbol" wire:click="revokeChurchApiKey" wire:confirm="Revoke this church’s API key? Heartbeat will fail until you generate a new one.">
                Revoke
            </x-filament::button>
        @endunless
    </div>
</div>
