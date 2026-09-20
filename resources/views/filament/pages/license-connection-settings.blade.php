<x-filament-panels::page>
    <div class="hw-license-connection">
        @include('filament.churches.instance-connection', [
            'church' => null,
            'revealedApiKey' => null,
            'showChurchFields' => false,
        ])
    </div>

    <style>
        .hw-license-connection {
            width: 100%;
            max-width: 100%;
        }
    </style>
</x-filament-panels::page>
