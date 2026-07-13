<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ComponentsSection::make('Informasi Umum')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Mobil')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state) . '-' . Str::lower(Str::random(4)))),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Select::make('category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                        ]),

                    TextInput::make('brand')
                        ->label('Merek')
                        ->maxLength(255),

                    TextInput::make('model_year')
                        ->label('Tahun')
                        ->maxLength(10),

                    TextInput::make('license_plate')
                        ->label('Nomor Plat')
                        ->maxLength(20),
                ]),

            ComponentsSection::make('Spesifikasi')
                ->columns(3)
                ->schema([
                    Select::make('transmission')
                        ->label('Transmisi')
                        ->options([
                            'manual' => 'Manual',
                            'automatic' => 'Automatic',
                        ])
                        ->required()
                        ->default('manual'),

                    Select::make('fuel_type')
                        ->label('Jenis Bahan Bakar')
                        ->options([
                            'bensin' => 'Bensin',
                            'diesel' => 'Diesel',
                            'listrik' => 'Listrik',
                            'hybrid' => 'Hybrid',
                        ])
                        ->required()
                        ->default('bensin'),

                    TextInput::make('seat_capacity')
                        ->label('Kapasitas Kursi')
                        ->numeric()
                        ->required()
                        ->default(4),

                    TextInput::make('luggage_capacity')
                        ->label('Kapasitas Bagasi')
                        ->numeric(),

                    TagsInput::make('features')
                        ->label('Fitur (Enter untuk tambah)')
                        ->placeholder('contoh: AC, Audio System, GPS')
                        ->columnSpan(2),
                ]),

            ComponentsSection::make('Harga')
                ->columns(2)
                ->schema([
                    TextInput::make('price_per_day')
                        ->label('Harga / Hari (Lepas Kunci)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required(),

                    TextInput::make('price_per_day_with_driver')
                        ->label('Harga / Hari (Dengan Sopir)')
                        ->numeric()
                        ->prefix('Rp'),
                ]),

            ComponentsSection::make('Deskripsi & Media')
                ->schema([
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(4)
                        ->columnSpanFull(),

                    FileUpload::make('images')
                        ->label('Foto Mobil (gambar pertama jadi thumbnail)')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->imageEditor()
                        ->directory('products')
                        ->columnSpanFull(),
                ]),

            ComponentsSection::make('Status')
                ->columns(3)
                ->schema([
                    Toggle::make('is_available')
                        ->label('Tersedia')
                        ->default(true),

                    Toggle::make('is_featured')
                        ->label('Tampilkan di Unggulan'),

                    TextInput::make('sort_order')
                        ->label('Urutan Tampil')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }
}
