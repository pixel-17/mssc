<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\SedeResource\Pages;
use App\Models\Sede;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Catálogo de sedes. latitud/longitud/radio_metros son las coordenadas
 * y el radio (metros) que MarcarRetornoAction usa para calcular
 * dentro_de_radio contra el GPS del retorno (Paso 5 del flujo).
 */
class SedeResource extends Resource
{
    protected static ?string $model = Sede::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Sedes';

    protected static ?string $modelLabel = 'sede';

    protected static ?string $pluralModelLabel = 'sedes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('direccion')
                ->maxLength(255),
            TextInput::make('latitud')
                ->label('Latitud')
                ->numeric()
                ->required()
                ->step('0.0000001'),
            TextInput::make('longitud')
                ->label('Longitud')
                ->numeric()
                ->required()
                ->step('0.0000001'),
            TextInput::make('radio_metros')
                ->label('Radio permitido (metros)')
                ->numeric()
                ->required()
                ->default(150)
                ->helperText('Radio alrededor de la sede dentro del cual el retorno se marca como "dentro de radio".'),
            Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('direccion')->limit(40)->toggleable(),
                TextColumn::make('radio_metros')->label('Radio (m)')->sortable(),
                IconColumn::make('activo')->boolean(),
                TextColumn::make('usuarios_count')->counts('usuarios')->label('Trabajadores'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSedes::route('/'),
            'create' => Pages\CreateSede::route('/create'),
            'edit' => Pages\EditSede::route('/{record}/edit'),
        ];
    }
}
