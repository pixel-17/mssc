<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\ConfiguracionResource\Pages;
use App\Models\Configuracion;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Parámetros clave-valor (reloj del jefe, tope de observaciones, bloque
 * de almuerzo, horas de sustento, días de subsanación). Filas fijas,
 * sembradas por ConfiguracionSeeder — admin solo puede EDITAR el
 * valor de cada clave, nunca crear ni borrar filas (ver getPages()).
 */
class ConfiguracionResource extends Resource
{
    protected static ?string $model = Configuracion::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Configuraciones';

    protected static ?string $modelLabel = 'configuración';

    protected static ?string $pluralModelLabel = 'configuraciones';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('clave')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('valor')
                ->required(),
            TextInput::make('descripcion')
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clave')->weight('bold'),
                TextColumn::make('valor'),
                TextColumn::make('descripcion')->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record = null): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfiguraciones::route('/'),
            'edit' => Pages\EditConfiguracion::route('/{record}/edit'),
        ];
    }
}
