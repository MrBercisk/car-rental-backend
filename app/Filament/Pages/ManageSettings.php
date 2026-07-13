<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Website';

    protected static ?string $title = 'Pengaturan Website';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::allAsArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pengaturan')
                    ->tabs([
                        Tab::make('Umum')
                            ->schema([
                                TextInput::make('site_name')
                                    ->label('Nama Website / Perusahaan')
                                    ->required(),

                                TextInput::make('site_tagline')
                                    ->label('Tagline'),

                                FileUpload::make('site_logo')
                                    ->label('Logo')
                                    ->image()
                                    ->directory('settings'),

                                FileUpload::make('site_favicon')
                                    ->label('Favicon')
                                    ->image()
                                    ->directory('settings'),

                                Textarea::make('site_description')
                                    ->label('Deskripsi Singkat Website')
                                    ->rows(3),
                            ])
                            ->columns(2),

                        Tab::make('Kontak')
                            ->schema([
                                TextInput::make('contact_phone')
                                    ->label('No. Telepon / WhatsApp')
                                    ->tel(),

                                TextInput::make('contact_email')
                                    ->label('Email')
                                    ->email(),

                                Textarea::make('contact_address')
                                    ->label('Alamat')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                TextInput::make('contact_maps_url')
                                    ->label('URL Google Maps')
                                    ->url()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Media Sosial')
                            ->schema([
                                TextInput::make('social_instagram')->label('Instagram URL')->url(),
                                TextInput::make('social_facebook')->label('Facebook URL')->url(),
                                TextInput::make('social_tiktok')->label('TikTok URL')->url(),
                                TextInput::make('social_youtube')->label('YouTube URL')->url(),
                            ])
                            ->columns(2),

                        Tab::make('SEO')
                            ->schema([
                                TextInput::make('seo_meta_title')
                                    ->label('Meta Title'),

                                Textarea::make('seo_meta_description')
                                    ->label('Meta Description')
                                    ->rows(3),

                                TextInput::make('seo_meta_keywords')
                                    ->label('Meta Keywords (pisahkan dengan koma)'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // Tentukan grup dari prefix key, contoh contact_phone -> group "contact"
            $group = explode('_', $key)[0] ?? 'general';
            Setting::set($key, $value, $group);
        }

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }
}