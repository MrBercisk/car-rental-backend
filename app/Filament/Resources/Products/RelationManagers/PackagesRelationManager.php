<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagesRelationManager extends RelationManager
{
    /** Matches the `packages()` hasMany relation defined on the Product model. */
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Paket & Harga Sewa';

    protected static ?string $modelLabel = 'Paket';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('package_id')
                ->label('Paket')
                ->relationship('package', 'name')
                ->required()
                ->searchable()
                ->preload()
                // Cegah paket yang sama dipilih dua kali untuk mobil yang sama.
                ->unique(
                    modifyRuleUsing: fn ($rule) => $rule->where('product_id', $this->getOwnerRecord()->id),
                    ignoreRecord: true
                ),

            TextInput::make('price')
                ->label('Harga')
                ->prefix('Rp')
                ->numeric()
                ->required()
                ->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('package.name')
                    ->label('Paket')
                    ->sortable(),

                TextColumn::make('package.duration_value')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->package->duration_unit === 'hour' ? 'Jam' : 'Hari')),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Paket'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}