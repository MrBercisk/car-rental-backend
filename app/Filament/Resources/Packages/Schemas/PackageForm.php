<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ComponentsSection::make('Detail Paket')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Paket')
                        ->placeholder('Contoh: 12 Jam, 24 Jam, Mingguan, Bulanan')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('duration_value')
                        ->label('Nilai Durasi')
                        ->helperText('Contoh: 12 untuk "12 Jam", 7 untuk "Mingguan", 30 untuk "Bulanan"')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    Select::make('duration_unit')
                        ->label('Satuan Durasi')
                        ->options([
                            'hour' => 'Jam',
                            'day' => 'Hari',
                        ])
                        ->required(),

                    TextInput::make('sort_order')
                        ->label('Urutan Tampil')
                        ->helperText('Angka kecil tampil lebih dulu')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }
}