<?php

namespace App\Filament\Resources\Devices;

use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Models\Device;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'POS';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('Device UUID')
                    ->default(fn (): string => (string) Str::uuid())
                    ->disabledOn('edit')
                    ->required()
                    ->uuid()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Fieldset::make('Printer settings')
                    ->schema([
                        TextInput::make('printer_model')
                            ->label('Printer model')
                            ->maxLength(255)
                            ->placeholder('Generic ESC/POS'),
                        TextInput::make('printer_ip_address')
                            ->label('IP address')
                            ->ip()
                            ->maxLength(45)
                            ->placeholder('192.168.1.50'),
                        TextInput::make('printer_port')
                            ->label('Port')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(9100),
                        Select::make('printer_paper_width')
                            ->label('Paper width')
                            ->options([
                                58 => '58 mm',
                                80 => '80 mm',
                            ]),
                    ])
                    ->columns(2),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label('Device UUID')
                    ->copyable()
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDevices::route('/'),
        ];
    }
}
