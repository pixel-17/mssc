<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\TurnoResource\Pages;
use App\Models\Turno;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\TimePicker;

/**
 * Horario de un trabajador en una fecha. Informativo para todos los
 * regímenes: 276 (ordinario) ya no depende de esto (usa el horario
 * único global en Configuraciones); 728 (rotativo) lo usa solo como
 * referencia, sin que bloquee nada. Único índice user_id+fecha (ver
 * migración) — el form valida lo mismo.
 */
class TurnoResource extends Resource
{
    protected static ?string $model = Turno::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Turnos';

    protected static ?string $modelLabel = 'turno';

    protected static ?string $pluralModelLabel = 'turnos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Trabajador')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('sede_id')
                ->label('Sede')
                ->relationship('sede', 'nombre')
                ->searchable()
                ->nullable(),
            DatePicker::make('fecha')
                ->required(),
            Toggle::make('es_descanso')
                ->label('Día de descanso')
                ->live()
                ->default(false),
            TimePicker::make('hora_inicio')
                ->seconds(false)
                ->required(fn ($get) => ! $get('es_descanso'))
                ->hidden(fn ($get) => (bool) $get('es_descanso')),
            TimePicker::make('hora_fin')
                ->seconds(false)
                ->required(fn ($get) => ! $get('es_descanso'))
                ->hidden(fn ($get) => (bool) $get('es_descanso')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usuario.name')->label('Trabajador')->searchable()->sortable(),
                TextColumn::make('sede.nombre')->label('Sede'),
                TextColumn::make('fecha')->date()->sortable(),
                TextColumn::make('hora_inicio')->time('H:i'),
                TextColumn::make('hora_fin')->time('H:i'),
                IconColumn::make('es_descanso')->boolean()->label('Descanso'),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Trabajador')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
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
            'index' => Pages\ListTurnos::route('/'),
            'create' => Pages\CreateTurno::route('/create'),
            'edit' => Pages\EditTurno::route('/{record}/edit'),
        ];
    }
}
