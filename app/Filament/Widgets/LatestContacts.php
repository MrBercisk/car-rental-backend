<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestContacts extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesan Kontak Terbaru')
            ->query(Contact::query()->latest())
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('subject')->label('Subjek')->limit(30),
                TextColumn::make('created_at')->label('Diterima')->since(),
                IconColumn::make('is_read')->label('Dibaca')->boolean(),
            ])
            ->paginated([5]);
    }
}
