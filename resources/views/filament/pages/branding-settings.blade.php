<x-filament-panels::page>
    <div class="hw-theme-settings">
        <form wire:submit="save" class="hw-theme-settings-form">
            {{ $this->form }}

            <div class="hw-form-actions">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save Theme</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </x-filament::button>
            </div>
        </form>

        <aside class="hw-theme-preview-panel" wire:ignore.self>
            <div class="hw-eyebrow" style="margin-bottom: 0.75rem;">Live preview</div>
            <style>{!! $previewCss !!}</style>
            <div id="hw-theme-preview" class="hw-theme-preview">
                <div class="hw-preview-card">
                    <div class="hw-eyebrow">Control Plane</div>
                    <div class="hw-preview-kpi">12</div>
                    <div class="hw-muted" style="font-size: 0.75rem;">Active church tenants</div>
                    <div class="hw-preview-actions">
                        <button type="button" class="hw-preview-btn hw-preview-btn-primary">Primary</button>
                        <button type="button" class="hw-preview-btn hw-preview-btn-ghost">Secondary</button>
                    </div>
                </div>

                <div class="hw-preview-card" style="margin-top: 0.75rem;">
                    <div class="hw-eyebrow">Login</div>
                    <p class="hw-preview-login-title">{{ $data['login_page']['welcome_title'] ?? 'Welcome back' }}</p>
                    <p class="hw-muted" style="font-size: 0.75rem;">{{ $data['login_page']['welcome_subtitle'] ?? '' }}</p>
                    <div class="hw-preview-actions">
                        <button type="button" class="hw-preview-btn hw-preview-btn-primary">Sign in</button>
                    </div>
                </div>
            </div>
            <p class="hw-muted" style="margin-top: 0.75rem; font-size: 0.75rem;">
                Preview uses unsaved values. Click Save Theme to apply across the admin and login page.
            </p>
        </aside>
    </div>

    <style>
        .hw-theme-settings {
            display: grid;
            gap: 1.5rem;
            align-items: start;
        }
        @media (min-width: 1280px) {
            .hw-theme-settings {
                grid-template-columns: minmax(0, 1fr) 22rem;
            }
        }
        .hw-theme-settings-form {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .hw-theme-preview-panel {
            position: sticky;
            top: 5rem;
            align-self: start;
            border: 1px solid var(--hw-card-border);
            border-radius: 12px;
            background: var(--hw-card-bg);
            padding: 1.15rem;
        }
        .hw-theme-preview {
            border-radius: 10px;
            background: var(--hw-canvas-bg);
            padding: 0.85rem;
        }
        .hw-preview-card {
            background: var(--hw-card-bg);
            border: 1px solid var(--hw-card-border);
            border-radius: var(--hw-card-radius);
            padding: 1rem 1.05rem;
            color: var(--hw-text);
        }
        .hw-preview-kpi {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--hw-btn-primary);
            margin: 0.35rem 0;
        }
        .hw-preview-login-title {
            margin: 0.35rem 0 0.15rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--hw-text);
        }
        .hw-preview-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-top: 0.9rem;
        }
        .hw-preview-btn {
            border: 0;
            border-radius: var(--hw-button-radius, 10px);
            padding: 0.45rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: default;
        }
        .hw-preview-btn-primary { background: var(--hw-btn-primary); color: #fff; }
        .hw-preview-btn-ghost {
            background: transparent;
            color: var(--hw-text);
            border: 1px solid var(--hw-card-border);
        }
        .hw-form-actions { margin-top: 1rem; }
    </style>
</x-filament-panels::page>
