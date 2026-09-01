<?php

namespace App\Filament\Resources\InventoryTransactions;

use App\Enums\InventoryTransactionType;
use App\Filament\Resources\InventoryTransactions\Pages\ManageInventoryTransactions;
use App\Models\InventoryTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory ledger';

    protected static ?string $modelLabel = 'inventory transaction';

    protected static ?string $pluralModelLabel = 'inventory ledger';

    protected static ?string $slug = 'inventory-ledger';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity_delta')
                    ->label('Quantity delta')
                    ->formatStateUsing(fn (string $state): string => str_starts_with($state, '-') ? $state : "+{$state}")
                    ->sortable(),
                TextColumn::make('type')
                    ->formatStateUsing(fn (InventoryTransactionType $state): string => Str::headline($state->value)),
                TextColumn::make('stock_before')
                    ->label('Stock before')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('stock_after')
                    ->label('Stock after')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('reason')
                    ->wrap(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryTransactions::route('/'),
        ];
    }
}
