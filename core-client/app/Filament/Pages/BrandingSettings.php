<?php

namespace App\Filament\Pages;

use App\Models\ChurchBranding;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?string $title = 'Church Branding';

    protected string $view = 'filament.pages.branding-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $churchId = auth()->user()?->church_id;
        $branding = ChurchBranding::query()->firstOrCreate(['church_id' => $churchId]);

        $this->form->fill($branding->toArray());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isChurchAdmin() || auth()->user()?->isSynodAdmin();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('logo')
                    ->image()
                    ->directory('branding/logos'),
                FileUpload::make('favicon')
                    ->image()
                    ->directory('branding/favicons'),
                ColorPicker::make('theme_color'),
                FileUpload::make('login_image')
                    ->image()
                    ->directory('branding/login'),
                Textarea::make('footer_text')
                    ->rows(3),
                TextInput::make('contact_info.email')
                    ->label('Contact Email')
                    ->email(),
                TextInput::make('contact_info.phone')
                    ->label('Contact Phone'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $churchId = auth()->user()?->church_id;

        ChurchBranding::query()->updateOrCreate(
            ['church_id' => $churchId],
            $data
        );

        Notification::make()
            ->title('Branding saved')
            ->success()
            ->send();
    }
}
