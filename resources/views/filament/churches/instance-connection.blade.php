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

    @if ($showChurchFields)
        <div class="hw-connection-row">
            <div class="hw-connection-label">Church ID</div>
            <div class="hw-connection-value">
                <input type="text" readonly value="{{ $churchId }}" class="hw-connection-input" x-ref="churchId">
                <div class="hw-connection-actions">
                    <button type="button" class="hw-connection-icon" title="Copy" x-on:click="navigator.clipboard.writeText($refs.churchId.value)">
                        <x-filament::icon icon="heroicon-m-clipboard" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    @endif

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

    @if ($showChurchFields)
        <div class="hw-connection-row">
            <div class="hw-connection-label">Instance API key</div>
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

<style>
    .hw-connection-panel {
        width: 100%;
        max-width: 100%;
        display: block;
        overflow: hidden;
        border: 1px solid var(--hw-card-border, rgba(255, 255, 255, 0.1));
        border-radius: 1rem;
        background: color-mix(in srgb, var(--hw-card-bg, transparent) 88%, transparent);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    .hw-connection-row {
        display: grid;
        grid-template-columns: 11.5rem minmax(0, 1fr);
        align-items: center;
        gap: 1.25rem;
        padding: 1rem 1.25rem;
    }

    .hw-connection-row + .hw-connection-row {
        border-top: 1px solid var(--hw-card-border, rgba(255, 255, 255, 0.08));
    }

    .hw-connection-label {
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--hw-text-muted, #94a3b8);
        line-height: 1.3;
    }

    .hw-connection-value {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: 0.75rem;
    }

    .hw-connection-input {
        flex: 1 1 auto;
        min-width: 0;
        width: 100%;
        height: 2.6rem;
        margin: 0;
        border: 1px solid var(--hw-input-border, rgba(255, 255, 255, 0.12));
        border-radius: 0.7rem;
        background: var(--hw-input-bg, rgba(255, 255, 255, 0.04));
        padding: 0 0.9rem;
        color: var(--hw-text, inherit);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.875rem;
        line-height: 2.6rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hw-connection-input:focus {
        outline: none;
        border-color: var(--hw-btn-primary, #3b82f6);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--hw-btn-primary, #3b82f6) 25%, transparent);
    }

    .hw-connection-input-danger {
        border-color: #f87171;
    }

    .hw-connection-actions {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.4rem;
    }

    .hw-connection-icon {
        display: inline-flex;
        width: 2.35rem;
        height: 2.35rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--hw-card-border, rgba(255, 255, 255, 0.12));
        border-radius: 0.65rem;
        background: transparent;
        color: var(--hw-text-muted, #94a3b8);
        cursor: pointer;
    }

    .hw-connection-icon:hover {
        background: color-mix(in srgb, var(--hw-card-bg, #fff) 80%, var(--hw-text, #000) 8%);
        color: var(--hw-text, inherit);
    }

    @media (max-width: 767px) {
        .hw-connection-row {
            grid-template-columns: 1fr;
            align-items: start;
            gap: 0.55rem;
            padding: 0.95rem 1rem;
        }

        .hw-connection-value {
            flex-wrap: wrap;
        }

        .hw-connection-input {
            flex-basis: 100%;
        }
    }
</style>
