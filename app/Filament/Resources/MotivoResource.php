<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\MotivoResource\Pages;
use App\Models\Motivo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Particular / Salud / Comisión de Servicio / Emergencia. Las banderas
 * de esta tabla son la única fuente de verdad de las reglas de negocio
 * por motivo (ver comentario en Motivo.php) — nunca hardcodear por
 * código en Actions/Policies.
 */
class MotivoResource extends Resource
{
    protected static ?string $model = Motivo::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Motivos';

    protected static ?string $modelLabel = 'motivo';

    protected static ?string $pluralModelLabel = 'motivos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos generales')
                ->columns(2)
                ->components([
                    TextInput::make('codigo')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Identificador interno, ej. PARTICULAR, SALUD, COMISION, EMERGENCIA.'),
                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(255),
                    Select::make('adjunto')
                        ->options([
                            'no' => 'No lleva adjunto',
                            'opcional' => 'Opcional',
                            'flexible' => 'Flexible (puede o no traerlo)',
                            'obligatorio' => 'Obligatorio',
                        ])
                        ->required()
                        ->default('no'),
                    Toggle::make('activo')->default(true),
                ]),
            Section::make('Reglas de negocio')
                ->description('Estas banderas son las que consultan las Actions del flujo — no hay lógica por nombre de motivo.')
                ->columns(2)
                ->components([
                    Toggle::make('suma_descuento')
                        ->label('Suma al contador mensual de descuento'),
                    Toggle::make('permite_bypass_aprobacion')
                        ->label('Permite bypass total de aprobación (nace autorizada)'),
                    Toggle::make('permite_cierre_sin_retorno')
                        ->label('Permite cerrar sin retorno físico'),
                    Toggle::make('requiere_sustento_en_retorno')
                        ->label('Requiere sustento al retorno (48h hábiles)'),
                    Toggle::make('participa_regla_exclusividad')
                        ->label('Participa en la regla de exclusividad (carril normal)')
                        ->default(true)
                        ->helperText('Desactivar solo para Emergencia, que corre en su propio carril.'),
                    Toggle::make('es_destino_reclasificacion')
                        ->label('Es el destino de reclasificación (Particular)')
                        ->helperText('Debe estar activo en exactamente un motivo.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->searchable()->sortable(),
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('adjunto')->badge(),
                IconColumn::make('permite_bypass_aprobacion')->boolean()->label('Bypass'),
                IconColumn::make('requiere_sustento_en_retorno')->boolean()->label('Sustento'),
                IconColumn::make('permite_cierre_sin_retorno')->boolean()->label('Cierre s/retorno'),
                IconColumn::make('activo')->boolean(),
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
            'index' => Pages\ListMotivos::route('/'),
            'create' => Pages\CreateMotivo::route('/create'),
            'edit' => Pages\EditMotivo::route('/{record}/edit'),
        ];
    }
}
