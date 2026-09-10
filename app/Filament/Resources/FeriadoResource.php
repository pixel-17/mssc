<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeriadoResource\Pages;
use App\Models\Feriado;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Catálogo de feriados usado por CalculadorDiasHabiles para las 48h
 * hábiles de sustento (Salud) y los 15 días hábiles de subsanación
 * de Emergencia (Paso 5 y Paso 6).
 */
class FeriadoResource extends Resource
{
    protected static ?string $model = Feriado::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Feriados';

    protected static ?string $modelLabel = 'feriado';

    protected static ?string $pluralModelLabel = 'feriados';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('descripcion')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date()->sortable(),
                TextColumn::make('descripcion'),
            ])
            ->defaultSort('fecha', 'desc')
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
            'index' => Pages\ListFeriados::route('/'),
            'create' => Pages\CreateFeriado::route('/create'),
            'edit' => Pages\EditFeriado::route('/{record}/edit'),
        ];
    }
}
