<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama Kategori')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
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