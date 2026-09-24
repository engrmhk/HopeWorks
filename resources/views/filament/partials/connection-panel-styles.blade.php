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
