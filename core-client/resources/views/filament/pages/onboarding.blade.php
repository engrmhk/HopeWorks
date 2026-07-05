<div>
    <x-filament-panels::page>
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <h2 class="text-xl font-semibold">Welcome to HopeWorks</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Complete this quick setup to personalize your church workspace. You can skip and finish later.
                </p>
            </div>

            <form wire:submit="complete">
                {{ $this->form }}

                <div class="mt-6 flex gap-3">
                    <x-filament::button type="submit">
                        Complete Setup
                    </x-filament::button>
                </div>
            </form>
        </div>
    </x-filament-panels::page>
</div>
