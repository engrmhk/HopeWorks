<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class Onboarding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'onboarding';

    protected string $view = 'filament.pages.onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'organization_name' => auth()->user()?->church?->name,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('organization_name')
                    ->label('Organization Name')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function complete(): void
    {
        $this->form->getState();

        auth()->user()->update([
            'onboarding_completed_at' => now(),
        ]);

        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    public function skip(): void
    {
        auth()->user()->update([
            'onboarding_completed_at' => now(),
        ]);

        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label('Skip for now')
                ->color('gray')
                ->action('skip'),
        ];
    }
}
