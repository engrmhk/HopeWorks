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

    $valueClass = 'block w-full min-w-0 rounded-lg border-0 bg-gray-50 px-3 py-2.5 font-mono text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10';
    $iconBtnClass = 'inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-gray-500 ring-1 ring-inset ring-gray-950/10 hover:bg-white hover:text-gray-900 dark:text-gray-400 dark:ring-white/10 dark:hover:bg-white/10 dark:hover:text-white';
    $labelClass = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $rowClass = 'grid grid-cols-1 gap-2 px-4 py-3.5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center sm:gap-6';
@endphp

<div
    class="w-full overflow-hidden rounded-xl bg-gray-50/80 ring-1 ring-gray-950/10 dark:bg-white/[0.03] dark:ring-white/10"
    x-data="{ showApiKey: false, showJwt: false }"
>
    <div class="{{ $rowClass }}">
        <div class="{{ $labelClass }}">Control Plane URL</div>
        <div class="flex min-w-0 items-center gap-2">
            <input type="text" readonly value="{{ $controlPlaneUrl }}" class="{{ $valueClass }}" x-ref="cpUrl">
            <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.cpUrl.value)">
                <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
            </button>
        </div>
    </div>

    @if ($showChurchFields)
        <div class="{{ $rowClass }} border-t border-gray-200/80 dark:border-white/10">
            <div class="{{ $labelClass }}">Church ID</div>
            <div class="flex min-w-0 items-center gap-2">
                <input type="text" readonly value="{{ $churchId }}" class="{{ $valueClass }}" x-ref="churchId">
                <button type="button" class="{{ $iconBtnClass }}" title="Copy" x-on:click="navigator.clipboard.writeText($refs.churchId.value)">
                    <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    <div class="{{ $rowClass }} border-t border-gray-200/80 dark:border-white/10">
        <div class="{{ $labelClass }}">JWT secret</div>
        <div class="flex min-w-0 flex-wrap items-center gap-2 sm:flex-nowrap">
            <input
                type="password"
                x-bind:type="showJwt ? 'text' : 'password'"
                wire:model="jwtSecretInput"
                x-ref="jwtField"
                autocomplete="off"
                class="{{ $valueClass }} @unless ($jwtReady) ring-danger-400 dark:ring-danger-500/60 @endunless"
                placeholder="Generate or paste 32+ characters"
            >
            <div class="flex shrink-0 items-center gap-1">
                <button type="button" class="{{ $iconBtnClass }}" title="Show or hide" x-on:click="showJwt = ! showJwt">
                    <span x-show="! showJwt">
                        <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                    </span>
                    <span x-cloak x-show="showJwt">
                        <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4" />
                    </span>
                </button>
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
    </div>

    @if ($showChurchFields)
        <div class="{{ $rowClass }} border-t border-gray-200/80 dark:border-white/10">
            <div class="{{ $labelClass }}">Instance API key</div>
            <div class="flex min-w-0 flex-wrap items-center gap-2 sm:flex-nowrap">
                <input
                    x-ref="apiKey"
                    type="password"
                    @if ($canRevealApiKey)
                        x-bind:type="showApiKey ? 'text' : 'password'"
                        value="{{ $revealedApiKey }}"
                    @else
                        value=""
                        placeholder="{{ $hasKey ? 'Saved — rotate to view' : 'No key yet' }}"
                    @endif
                    readonly
                    autocomplete="off"
                    class="{{ $valueClass }}"
                >
                <div class="flex shrink-0 items-center gap-1">
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
        </div>
    @endif
</div>
