@php
    use App\Support\LicenseJwtSecret;

    /** @var \App\Models\Synod|null $synod */

    $revealedApiKey = $revealedApiKey ?? null;
    $hasKey = $synod && $synod->hasApiKey();
    $canRevealApiKey = filled($revealedApiKey);
    $jwtReady = LicenseJwtSecret::isReady();
    $controlPlaneUrl = rtrim((string) config('app.url'), '/');
@endphp

<div class="hw-connection-panel" x-data="{ showApiKey: false, showJwt: false }">
    <div class="hw-connection-row">
        <div class="hw-connection-label">Control Plane URL</div>
        <div class="hw-connection-value">
            <input type="text" readonly value="{{ $controlPlaneUrl }}" class="hw-connection-input" x-ref="cpUrl">
            <div class="hw-connection-actions">
                <button type="button" class="hw-connection-icon" title="Copy" x-on:click="navigator.clipboard.writeText($refs.cpUrl.value)">
                    <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>

    <div class="hw-connection-row">
        <div class="hw-connection-label">Synod ID</div>
        <div class="hw-connection-value">
            <input type="text" readonly value="{{ $synod?->id }}" class="hw-connection-input" x-ref="synodId">
            <div class="hw-connection-actions">
                <button type="button" class="hw-connection-icon" title="Copy" x-on:click="navigator.clipboard.writeText($refs.synodId.value)">
                    <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>

    <div class="hw-connection-row">
        <div class="hw-connection-label">JWT secret</div>
        <div class="hw-connection-value">
            <input
                type="password"
                x-bind:type="showJwt ? 'text' : 'password'"
                wire:model="jwtSecretInput"
                x-ref="jwtField"
                autocomplete="off"
                class="hw-connection-input @unless ($jwtReady) hw-connection-input-danger @endunless"
                placeholder="Generate or paste 32+ characters"
            >
            <div class="hw-connection-actions">
                <button type="button" class="hw-connection-icon" title="Show or hide" x-on:click="showJwt = ! showJwt">
                    <span x-show="! showJwt">
                        <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                    </span>
                    <span x-cloak x-show="showJwt">
                        <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4" />
                    </span>
                </button>
                <button type="button" class="hw-connection-icon" title="Copy" x-on:click="navigator.clipboard.writeText($refs.jwtField.value)">
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

    <div class="hw-connection-row">
        <div class="hw-connection-label">Synod API key</div>
        <div class="hw-connection-value">
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
                class="hw-connection-input"
            >
            <div class="hw-connection-actions">
                @if ($canRevealApiKey)
                    <button type="button" class="hw-connection-icon" title="Show or hide" x-on:click="showApiKey = ! showApiKey">
                        <span x-show="! showApiKey">
                            <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                        </span>
                        <span x-cloak x-show="showApiKey">
                            <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4" />
                        </span>
                    </button>
                    <button type="button" class="hw-connection-icon" title="Copy" x-on:click="navigator.clipboard.writeText($refs.apiKey.value)">
                        <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                    </button>
                @endif
                @unless ($hasKey)
                    <x-filament::button type="button" size="sm" color="success" wire:click="generateSynodApiKey">
                        Generate
                    </x-filament::button>
                @else
                    <x-filament::button type="button" size="sm" color="gray" wire:click="rotateSynodApiKey" wire:confirm="Rotate this synod API key? The old key stops working immediately.">
                        Rotate
                    </x-filament::button>
                    <x-filament::button type="button" size="sm" color="danger" outlined wire:click="revokeSynodApiKey" wire:confirm="Revoke this synod API key?">
                        Revoke
                    </x-filament::button>
                @endunless
            </div>
        </div>
    </div>
</div>

@include('filament.partials.connection-panel-styles')
