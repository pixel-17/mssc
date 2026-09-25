<?php

namespace App\Actions\Usuario;

use App\Models\User;

/**
 * Edición de un trabajador existente por Jefe de Área / Jefe
 * Inmediato (ver UsuarioController::update() / EditarUsuarioRequest).
 *
 * sede_id y unidad_organica_id: mismo criterio que CrearUsuarioAction.
 * Un Jefe Inmediato "puro" (! $esJefeDeArea) nunca los cambia, aunque
 * lleguen en $datos — el trabajador se queda en la sede/unidad del
 * editor, de forma implícita. Un Jefe de Área sí puede reubicarlo
 * dentro de su propia área (EditarUsuarioRequest ya validó que la
 * unidad elegida cae en su subárbol).
 *
 * El turno NO se toca aquí a propósito: ya existe una pantalla
 * dedicada para eso (Turnos\ConfiguracionTurnoForm, autorizada con
 * User::puedeGestionarTurnoDe — alcance igual o más amplio que
 * UserPolicy::editar()). Reimplementar esa lógica aquí duplicaría la
 * fuente de verdad de GeneradorTurnoMensualService::cargarConfiguracion
 * con su propia validación; mismo criterio ya sigue UsuarioAdminForm,
 * que tampoco re-edita el turno de un trabajador que ya tiene uno.
 */
class EditarUsuarioAction
{
    /**
     * @param  array{name:string,apellido:string,email:string,sede_id:?int,unidad_organica_id:?int}  $datos
     */
    public function ejecutar(User $trabajador, array $datos, bool $esJefeDeArea): User
    {
        $trabajador->update([
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'sede_id' => $esJefeDeArea ? ($datos['sede_id'] ?? null) : $trabajador->sede_id,
            'unidad_organica_id' => $esJefeDeArea ? $datos['unidad_organica_id'] : $trabajador->unidad_organica_id,
        ]);

        return $trabajador->fresh();
    }
}
