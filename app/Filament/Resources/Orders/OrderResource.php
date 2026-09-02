<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('source')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('payment_status')->badge(),
                        TextEntry::make('payment_method'),
                        TextEntry::make('ordered_at')
                            ->dateTime('d-m-Y H:i', timezone: Order::SHOP_TIMEZONE),
                        TextEntry::make('cashier_name')->label('Cashier'),
                        TextEntry::make('device_name')->label('Device'),
                    ])
                    ->columns(4),
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Description'),
                                TableColumn::make('Article number'),
                                TableColumn::make('Quantity'),
                                TableColumn::make('VAT'),
                                TableColumn::make('Unit price'),
                                TableColumn::make('Line total'),
                            ])
                            ->schema([
                                TextEntry::make('description'),
                                TextEntry::make('article_number')->placeholder('Manual'),
                                TextEntry::make('quantity')->numeric(decimalPlaces: 3),
                                TextEntry::make('tax_rate_basis_points')
                                    ->label('VAT')
                                    ->formatStateUsing(fn (int $state): string => intdiv($state, 100).'%'),
                                TextEntry::make('unit_price_including_tax_cents')
                                    ->money('EUR', divideBy: 100),
                                TextEntry::make('line_including_tax_cents')
                                    ->money('EUR', divideBy: 100),
                            ]),
                    ]),
                Section::make('Totals')
                    ->schema([
                        TextEntry::make('subtotal_excluding_tax_cents')
                            ->label('Excluding VAT')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('tax_9_cents')
                            ->label('VAT 9%')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('tax_21_cents')
                            ->label('VAT 21%')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('tax_total_cents')
                            ->label('Total VAT')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('total_including_tax_cents')
                            ->label('Total including VAT')
                            ->money('EUR', divideBy: 100),
                    ])
                    ->columns(5),
                Section::make('Payment')
                    ->schema([
                        TextEntry::make('payment.method'),
                        TextEntry::make('payment.status'),
                        TextEntry::make('payment.amount_cents')
                            ->label('Amount')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('payment.tendered_amount_cents')
                            ->label('Tendered')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('payment.change_due_cents')
                            ->label('Change')
                            ->money('EUR', divideBy: 100),
                        TextEntry::make('payment.paid_at')
                            ->dateTime('d-m-Y H:i', timezone: Order::SHOP_TIMEZONE),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                TextColumn::make('ordered_at')
                    ->dateTime('d-m-Y H:i', timezone: Order::SHOP_TIMEZONE)
                    ->sortable(),
                TextColumn::make('cashier_name')
                    ->label('Cashier')
                    ->searchable(),
                TextColumn::make('device_name')
                    ->label('Device'),
                TextColumn::make('total_including_tax_cents')
                    ->label('Total')
                    ->money('EUR', divideBy: 100)
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('ordered_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
