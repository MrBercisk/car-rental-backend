<?php

namespace App\Filament\Resources\Contacts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->disabled(),
            TextInput::make('email')->label('Email')->required()->disabled(),
            TextInput::make('phone')->label('No. HP')->disabled(),
            TextInput::make('subject')->label('Subjek')->disabled(),
            Textarea::make('message')->label('Pesan')->rows(5)->disabled()->columnSpanFull(),
            Toggle::make('is_read')->label('Sudah Dibaca'),
        ]);
    }
}
