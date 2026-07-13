<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),

            TextInput::make('role')
                ->label('Keterangan')
                ->placeholder('contoh: Wisatawan dari Jakarta')
                ->maxLength(255),

            FileUpload::make('photo')
                ->label('Foto')
                ->image()
                ->avatar()
                ->directory('testimonials'),

            Select::make('rating')
                ->label('Rating')
                ->options([
                    5 => '⭐⭐⭐⭐⭐ (5)',
                    4 => '⭐⭐⭐⭐ (4)',
                    3 => '⭐⭐⭐ (3)',
                    2 => '⭐⭐ (2)',
                    1 => '⭐ (1)',
                ])
                ->default(5)
                ->required(),

            Textarea::make('message')
                ->label('Isi Testimoni')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

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
