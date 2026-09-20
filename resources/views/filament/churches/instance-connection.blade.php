@php
    use App\Services\ChurchInstanceAccessService;
    use App\Support\LicenseJwtSecret;

    /** @var \App\Models\Church|null $church */

    $revealedApiKey = $revealedApiKey ?? null;
    $showChurchFields = $showChurchFields ?? ($church !== null);
    $hasKey = $showChurchFields && $church && app(ChurchInstanceAccessService::class)->hasApiKey($church);
    $canRevealApiKey = $showChurchFields && filled($revealedApiKey);
    $jwtReady = LicenseJwtSecret::isReady();
    $controlPlaneUrl = rtrim((string) config('app.url'), '/');
    $churchId = $church?->id;

    $inputClass = 'w-full rounded-lg border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white';
    $iconBtnClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white';
@endphp

<div class="space-y-3" x-data="{ showApiKey: false }">
    <div>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Control Plane URL</label>
        <div class="mt-1 flex gap-1">
            <input type="text" readonly value="{{ $controlPlaneUrl }}" class="{{ $inputClass }}" x-ref="cpUrl">
            <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.cpUrl.value)">
                <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
            </button>
        </div>
    </div>

    @if ($showChurchFields)
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Church ID</label>
            <div class="mt-1 flex gap-1">
                <input type="text" readonly value="{{ $churchId }}" class="{{ $inputClass }}" x-ref="churchId">
                <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.churchId.value)">
                    <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    <div>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">JWT secret</label>
        <div class="mt-1 flex flex-wrap gap-1">
            <input
                type="text"
                wire:model="jwtSecretInput"
                x-ref="jwtField"
                class="{{ $inputClass }} min-w-0 flex-1 @unless ($jwtReady) border-danger-400 dark:border-danger-500/60 @endunless"
                placeholder="Generate or paste 32+ characters"
            >
            <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.jwtField.value)">
                <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
            </button>
            <x-filament::button type="button" size="sm" color="gray" wire:click="generateLicenseJwtSecret" wire:confirm="Generate a new JWT secret? Every church must paste the new value.">
                Generate
            </x-filament::button>
            <x-filament::button type="button" size="sm" wire:click="saveLicenseJwtSecret">
                Save
            </x-filament::button>
        </div>
    </div>

    @if ($showChurchFields)
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Instance API key</label>
            <div class="mt-1 flex flex-wrap gap-1">
                <input
                    x-ref="apiKey"
                    @if ($canRevealApiKey)
                        :type="showApiKey ? 'text' : 'password'"
                        value="{{ $revealedApiKey }}"
                    @else
                        type="password"
                        value=""
                        placeholder="{{ $hasKey ? 'Saved — rotate to view' : 'No key yet' }}"
                    @endif
                    readonly
                    autocomplete="off"
                    class="{{ $inputClass }} min-w-0 flex-1"
                >
                @if ($canRevealApiKey)
                    <button type="button" class="{{ $iconBtnClass }}" title="Show or hide" x-on:click="showApiKey = ! showApiKey">
                        <span x-show="! showApiKey">
                            <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                        </span>
                        <span x-cloak x-show="showApiKey">
                            <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4" />
                        </span>
                    </button>
                    <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.apiKey.value)">
                        <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                    </button>
                @endif
                @unless ($hasKey)
                    <x-filament::button type="button" size="sm" color="success" wire:click="generateChurchApiKey">
                        Generate
                    </x-filament::button>
                @else
                    <x-filament::button type="button" size="sm" color="gray" wire:click="rotateChurchApiKey" wire:confirm="Rotate this API key? The old key stops working immediately.">
                        Rotate
                    </x-filament::button>
                    <x-filament::button type="button" size="sm" color="danger" outlined wire:click="revokeChurchApiKey" wire:confirm="Revoke this API key?">
                        Revoke
                    </x-filament::button>
                @endunless
            </div>
        </div>
    @endif
</div>
