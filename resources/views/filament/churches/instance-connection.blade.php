@php
    use App\Services\ChurchInstanceAccessService;
    use App\Support\LicenseJwtSecret;

    /** @var \App\Models\Church|null $church */

    $revealedApiKey = $revealedApiKey ?? null;
    $showChurchFields = $showChurchFields ?? ($church !== null);
    $hasKey = $showChurchFields && $church && app(ChurchInstanceAccessService::class)->hasApiKey($church);
    $jwtReady = LicenseJwtSecret::isReady();
    $controlPlaneUrl = rtrim((string) config('app.url'), '/');
    $churchId = $church?->id;
@endphp

<div class="space-y-4" x-data>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        @if ($showChurchFields)
            Copy these into the church app <strong>Settings → System → Control Plane connection</strong>, then click Sync Now.
        @else
            This JWT secret is shared by every church. Generate or paste it here, then copy it into each church System page.
        @endif
    </p>

    @unless ($jwtReady)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-950/40 dark:text-danger-200">
            No JWT secret saved yet. Click <strong>Generate JWT secret</strong> below, or paste the secret from the church System page and click <strong>Save JWT secret</strong>.
        </div>
    @endunless

    @if ($showChurchFields && filled($revealedApiKey))
        <div class="rounded-xl border-2 border-warning-400 bg-warning-50 p-4 dark:border-warning-500/50 dark:bg-warning-950/30">
            <p class="text-sm font-semibold text-warning-900 dark:text-warning-100">Instance API key — copy now</p>
            <p class="mt-1 text-xs text-warning-800 dark:text-warning-200">
                Paste into the church <strong>Instance API key</strong> field. This is not the JWT secret.
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="text" readonly value="{{ $revealedApiKey }}" class="w-full rounded-lg border-warning-300 bg-white px-3 py-2 font-mono text-sm text-gray-950 dark:bg-gray-900 dark:text-white" x-ref="revealedKey">
                <button type="button" class="rounded-lg bg-warning-500 px-3 py-2 text-sm font-semibold text-white" x-on:click="navigator.clipboard.writeText($refs.revealedKey.value)">Copy key</button>
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
        @if ($showChurchFields)
            <div>
                <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Control Plane church ID</label>
                <div class="mt-1 flex gap-2">
                    <input type="text" readonly value="{{ $churchId }}" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" x-ref="churchId">
                    <button type="button" class="rounded-lg px-3 text-sm font-medium text-primary-600" x-on:click="navigator.clipboard.writeText($refs.churchId.value)">Copy</button>
                </div>
            </div>
        @endif
        <div class="sm:col-span-2">
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">License JWT secret</label>
            <div class="mt-1 flex gap-2">
                <input
                    type="text"
                    wire:model="jwtSecretInput"
                    x-ref="jwtField"
                    class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                    placeholder="Generate or paste 32+ characters"
                >
                <button type="button" class="rounded-lg px-3 text-sm font-medium text-primary-600" x-on:click="navigator.clipboard.writeText($refs.jwtField.value)">Copy</button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Shared signing password. Same value on every church System page. Not the Instance API key.
            </p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-filament::button type="button" color="warning" icon="heroicon-m-sparkles" wire:click="generateLicenseJwtSecret" wire:confirm="Generate a new shared JWT secret? Every church must paste the new value or Sync Now will fail.">
            Generate JWT secret
        </x-filament::button>
        <x-filament::button type="button" color="primary" icon="heroicon-m-check" wire:click="saveLicenseJwtSecret">
            Save JWT secret
        </x-filament::button>

        @if ($showChurchFields)
            @unless ($hasKey)
                <x-filament::button type="button" color="success" icon="heroicon-m-key" wire:click="generateChurchApiKey">
                    Generate API key
                </x-filament::button>
            @else
                <x-filament::button type="button" color="gray" icon="heroicon-m-arrow-path" wire:click="rotateChurchApiKey" wire:confirm="Rotate this church’s API key? The old key stops working immediately.">
                    Rotate API key
                </x-filament::button>
                <x-filament::button type="button" color="danger" outlined icon="heroicon-o-no-symbol" wire:click="revokeChurchApiKey" wire:confirm="Revoke this church’s API key?">
                    Revoke API key
                </x-filament::button>
            @endunless
        @endif
    </div>
</div>
