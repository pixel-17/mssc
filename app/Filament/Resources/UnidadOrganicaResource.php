<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\UnidadOrganicaResource\Pages;
use App\Models\UnidadOrganica;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Árbol de organigrama. El jefe_id de esta unidad es el Jefe Inmediato
 * de sus miembros; el jefe_id de la unidad padre es el Jefe de Área
 * (ver UnidadOrganica::jefeInmediato()/jefeArea()). `tipo` es solo
 * decorativo, nunca condiciona el escalamiento de papeletas.
 */
class UnidadOrganicaResource extends Resource
{
    protected static ?string $model = UnidadOrganica::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static string | UnitEnum | null $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Unidades orgánicas';

    protected static ?string $modelLabel = 'unidad orgánica';

    protected static ?string $pluralModelLabel = 'unidades orgánicas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Select::make('tipo')
                ->options([
                    'alta_direccion' => 'Alta dirección',
                    'consultivo' => 'Consultivo',
                    'control' => 'Control',
                    'apoyo' => 'Apoyo',
                    'apoyo_alcaldia' => 'Apoyo a alcaldía',
                    'asesoramiento' => 'Asesoramiento',
                    'linea_2do_nivel' => 'Línea - 2do nivel',
                    'linea_3er_nivel' => 'Línea - 3er nivel',
                ])
                ->nullable()
                ->helperText('Solo pinta el organigrama, no afecta el escalamiento de papeletas.'),
            Select::make('parent_id')
                ->label('Unidad padre')
                ->relationship(
                    name: 'padre',
                    titleAttribute: 'nombre',
                    modifyQueryUsing: function (Builder $query, $record) {
                        // No puede ser su propio padre ni el de ninguno de sus descendientes,
                        // o el árbol se vuelve cíclico (ver UnidadOrganica::descendantIds()).
                        if ($record) {
                            $query->whereKeyNot($record->id)
                                ->whereNotIn('id', $record->descendantIds());
                        }

                        return $query;
                    },
                )
                ->searchable()
                ->nullable(),
            Select::make('jefe_id')
                ->label('Jefe de la unidad')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('padre.nombre')->label('Unidad padre')->placeholder('— (raíz)'),
                TextColumn::make('tipo')->badge(),
                TextColumn::make('jefe.name')->label('Jefe'),
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
            'index' => Pages\ListUnidadesOrganicas::route('/'),
            'create' => Pages\CreateUnidadOrganica::route('/create'),
            'edit' => Pages\EditUnidadOrganica::route('/{record}/edit'),
        ];
    }
}
