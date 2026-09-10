<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\HorarioRrhhResource\Pages;
use App\Models\HorarioRrhh;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Horario único de RRHH para toda la municipalidad, una fila fija por
 * día de la semana (sembradas por HorarioRrhhSeeder). Se consulta en
 * cada aprobación de jefe (RrhhHorarioService) — admin solo edita
 * hora_inicio/hora_fin/activo, nunca crea ni borra días.
 */
class HorarioRrhhResource extends Resource
{
    protected static ?string $model = HorarioRrhh::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Horario de RRHH';

    protected static ?string $modelLabel = 'horario de RRHH';

    protected static ?string $pluralModelLabel = 'horario de RRHH';

    private const DIAS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TimePicker::make('hora_inicio')
                ->seconds(false)
                ->required(),
            TimePicker::make('hora_fin')
                ->seconds(false)
                ->required(),
            Toggle::make('activo')
                ->label('RRHH atiende este día'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dia_semana')
                    ->label('Día')
                    ->formatStateUsing(fn (int $state) => self::DIAS[$state] ?? $state)
                    ->weight('bold'),
                TextColumn::make('hora_inicio')->time('H:i'),
                TextColumn::make('hora_fin')->time('H:i'),
                IconColumn::make('activo')->boolean(),
            ])
            ->defaultSort('dia_semana')
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
            'index' => Pages\ListHorariosRrhh::route('/'),
            'edit' => Pages\EditHorarioRrhh::route('/{record}/edit'),
        ];
    }
}
