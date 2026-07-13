<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255),

            TextInput::make('subtitle')
                ->label('Sub Judul')
                ->maxLength(255),

            FileUpload::make('image')
                ->label('Gambar Banner (disarankan 1920x800px)')
                ->image()
                ->required()
                ->directory('banners')
                ->imageEditor()
                ->columnSpanFull(),

            TextInput::make('button_text')
                ->label('Teks Tombol')
                ->maxLength(50)
                ->placeholder('contoh: Pesan Sekarang'),

            TextInput::make('button_url')
                ->label('URL Tombol')
                ->url()
                ->placeholder('https://... atau /kontak'),

            TextInput::make('sort_order')
                ->label('Urutan Tampil')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
