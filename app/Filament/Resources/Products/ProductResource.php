<?php

namespace App\Filament\Resources\Products;

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Jobs\SyncProductStockToWooCommerce;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('article_number')
                    ->label('Article number')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('barcode')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('selling_price_including_tax_cents')
                    ->label('Selling price incl. VAT')
                    ->suffix('cents')
                    ->required()
                    ->integer()
                    ->minValue(0),
                TextInput::make('purchase_price_excluding_tax_cents')
                    ->label('Purchase price excl. VAT')
                    ->suffix('cents')
                    ->required()
                    ->integer()
                    ->minValue(0),
                TextInput::make('stock_on_hand')
                    ->label('Stock on hand')
                    ->disabled()
                    ->saved(false)
                    ->default('0.000'),
                TextInput::make('minimum_stock')
                    ->label('Minimum stock')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->default('0.000'),
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('tax_rate_id')
                    ->label('VAT rate')
                    ->relationship(
                        name: 'taxRate',
                        titleAttribute: 'label',
                        modifyQueryUsing: fn (Builder $query): Builder => self::activeV1TaxRates($query),
                    )
                    ->scopedExists(
                        TaxRate::class,
                        'id',
                        fn (Builder $query): Builder => self::activeV1TaxRates($query),
                    )
                    ->preload()
                    ->required(),
                TextInput::make('woocommerce_product_id')
                    ->label('WooCommerce product ID')
                    ->integer()
                    ->minValue(1)
                    ->rule(fn (Get $get, ?Product $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if ($value === null || filled($get('woocommerce_variation_id'))) {
                            return;
                        }

                        if (Product::query()
                            ->where('woocommerce_product_id', $value)
                            ->whereNull('woocommerce_variation_id')
                            ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->id))
                            ->exists()) {
                            $fail('This WooCommerce product is already mapped.');
                        }
                    }),
                TextInput::make('woocommerce_variation_id')
                    ->label('WooCommerce variation ID')
                    ->integer()
                    ->minValue(1)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if ($value !== null && blank($get('woocommerce_product_id'))) {
                            $fail('A WooCommerce parent product ID is required for a variation.');
                        }
                    })
                    ->unique(ignoreRecord: true),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article_number')
                    ->label('Article number')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('selling_price_including_tax_cents')
                    ->label('Selling incl. VAT')
                    ->numeric()
                    ->suffix(' cents')
                    ->sortable(),
                TextColumn::make('purchase_price_excluding_tax_cents')
                    ->label('Purchase excl. VAT')
                    ->numeric()
                    ->suffix(' cents')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('stock_on_hand')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('woocommerce_stock_sync_status')
                    ->label('Woo stock sync')
                    ->state(fn (Product $record): string => self::wooCommerceStockSyncStatus($record))
                    ->badge()
                    ->color(fn (Product $record): string => match (true) {
                        $record->woocommerce_product_id === null => 'gray',
                        $record->woocommerce_stock_sync_failed_at !== null => 'danger',
                        $record->woocommerce_stock_synced_at !== null => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('minimum_stock')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('taxRate.label')
                    ->label('VAT')
                    ->sortable(),
                TextColumn::make('woocommerce_product_id')
                    ->label('Woo product ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('woocommerce_variation_id')
                    ->label('Woo variation ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('adjustStock')
                    ->label('Adjust stock')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->authorize('adjustStock')
                    ->schema([
                        TextInput::make('quantity_delta')
                            ->label('Quantity change')
                            ->helperText('Use a negative number to reduce stock.')
                            ->required()
                            ->rules(['regex:/\A-?(?!0+(?:\.0{1,3})?\z)\d{1,12}(?:\.\d{1,3})?\z/']),
                        Textarea::make('reason')
                            ->required()
                            ->maxLength(1000),
                        Hidden::make('idempotency_key')
                            ->default(fn (): string => (string) Str::uuid())
                            ->required()
                            ->uuid(),
                    ])
                    ->action(function (Product $record, array $data, PostInventory $postInventory): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            throw new AuthorizationException;
                        }

                        $transaction = $postInventory->handle(
                            product: $record,
                            quantityDelta: (string) $data['quantity_delta'],
                            type: InventoryTransactionType::ManualAdjustment,
                            idempotencyKey: (string) $data['idempotency_key'],
                            actor: $actor,
                            reason: (string) $data['reason'],
                        );

                        Notification::make()
                            ->success()
                            ->title('Stock adjusted')
                            ->body("New stock: {$transaction->stock_after}")
                            ->send();
                    }),
                Action::make('retryWooCommerceStockSync')
                    ->label('Retry Woo stock sync')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize('retryWooCommerceStockSync')
                    ->visible(fn (Product $record): bool => $record->woocommerce_product_id !== null
                        && $record->woocommerce_stock_sync_failed_at !== null)
                    ->disabled(fn (Product $record): bool => ! str_ends_with($record->stock_on_hand, '.000'))
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        SyncProductStockToWooCommerce::dispatch($record->id);

                        Notification::make()
                            ->success()
                            ->title('Woo stock sync queued')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('title');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }

    /**
     * @param  Builder<TaxRate>  $query
     * @return Builder<TaxRate>
     */
    private static function activeV1TaxRates(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereIn('rate_basis_points', [900, 2100]);
    }

    private static function wooCommerceStockSyncStatus(Product $product): string
    {
        if ($product->woocommerce_product_id === null) {
            return 'Local only';
        }

        if ($product->woocommerce_stock_sync_failed_at !== null) {
            return 'Failed: '.Str::headline($product->woocommerce_stock_sync_failure_code ?? 'unexpected failure');
        }

        return $product->woocommerce_stock_synced_at === null ? 'Awaiting sync' : 'Synced';
    }
}
