<x-filament-panels::page>
    <div class="w-full max-w-none">
        @include('filament.churches.instance-connection', [
            'church' => null,
            'revealedApiKey' => null,
            'showChurchFields' => false,
        ])
    </div>
</x-filament-panels::page>
