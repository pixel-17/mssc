<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Gestión de usuarios SOLO para admin — es el fin de la pirámide (ver
 * UserPolicy), puede crear cualquier usuario en cualquier unidad y con
 * cualquier rol, sin las restricciones de área que sí aplican a Jefe
 * de Área / Jefe Inmediato (ver Usuario\UsuarioController, la vía que
 * ellos usan en Blade+Livewire, nunca este panel).
 *
 * jefe_inmediato_id / jefe_area_id NO se editan aquí: se recalculan
 * solos vía UserObserver en cuanto se guarda unidad_organica_id. Para
 * que alguien sea "Jefe Inmediato" de una unidad, asígnalo como
 * jefe_id de esa unidad desde UnidadOrganicaResource.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'Usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombres')->required()->maxLength(255),
            TextInput::make('apellido')->label('Apellidos')->required()->maxLength(255),
            TextInput::make('dni')->label('DNI')->required()->minLength(8)->maxLength(8)->unique(ignoreRecord: true),
            TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context) => $context === 'create')
                ->helperText('Déjalo en blanco al editar para no cambiar la contraseña actual.'),
            Select::make('regimen')
                ->label('Régimen')
                ->options(['276' => '276 (día)', '728' => '728 (rotativo)'])
                ->required(),
            Select::make('sede_id')
                ->label('Sede')
                ->options(fn () => Sede::where('activo', true)->pluck('nombre', 'id'))
                ->searchable()
                ->nullable(),
            Select::make('unidad_organica_id')
                ->label('Unidad orgánica')
                ->options(fn () => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->nullable()
                ->helperText('Determina automáticamente su jefe inmediato y jefe de área.'),
            Select::make('roles')
                ->label('Rol')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->helperText('admin y rrhh son roles globales. "trabajador" es el rol de todos los demás (incluye a quienes además son Jefe Inmediato o Jefe de Área por posición en el organigrama).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')->label('Nombre')->searchable(['name', 'apellido']),
                TextColumn::make('dni')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('regimen')->badge(),
                TextColumn::make('unidadOrganica.nombre')->label('Unidad')->placeholder('—'),
                TextColumn::make('roles.name')->label('Rol(es)')->badge(),
            ])
            ->filters([
                SelectFilter::make('regimen')->options(['276' => '276', '728' => '728']),
                SelectFilter::make('roles')->relationship('roles', 'name'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
