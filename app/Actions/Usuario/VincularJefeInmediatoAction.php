<?php

namespace App\Actions\Usuario;

use App\Exceptions\UsuarioException;
use App\Models\User;

/**
 * "Los trabajadores pueden tener más de un jefe inmediato: al vincular
 * hay que indicar que ya tiene y, si el jefe está de acuerdo, añadir
 * uno más" — este es ese flujo, en dos pasos:
 *
 * 1. buscarPorDni(): localiza al trabajador y devuelve quiénes ya son
 *    su(s) jefe(s) inmediato(s) (el automático de su unidad + los
 *    adicionales), para que la vista se lo muestre al jefe que está
 *    creando/vinculando ANTES de pedir confirmación.
 * 2. vincular(): recién aquí, con $confirmado = true explícito desde el
 *    formulario, se inserta la fila en jefes_inmediatos_adicionales.
 */
class VincularJefeInmediatoAction
{
    public function buscarPorDni(string $dni): ?User
    {
        return User::where('dni', $dni)->first();
    }

    /**
     * @return array<int, string> nombres de los jefes inmediatos actuales del trabajador
     */
    public function jefesActualesDe(User $trabajador): array
    {
        $jefes = collect();

        if ($trabajador->jefeInmediato) {
            $jefes->push($trabajador->jefeInmediato->nombre_completo);
        }

        foreach ($trabajador->jefesInmediatosAdicionales as $adicional) {
            $jefes->push($adicional->nombre_completo);
        }

        return $jefes->unique()->values()->all();
    }

    public function vincular(User $jefe, User $trabajador, bool $confirmado): void
    {
        if ($jefe->esJefeInmediatoDe($trabajador)) {
            throw new UsuarioException('Ya eres jefe inmediato de este trabajador.');
        }

        if (! empty($this->jefesActualesDe($trabajador)) && ! $confirmado) {
            throw new UsuarioException(
                'Este trabajador ya tiene jefe(s) inmediato(s) asignado(s). Debes confirmar explícitamente para agregarte como uno más.'
            );
        }

        $trabajador->jefesInmediatosAdicionales()->attach($jefe->id, [
            'asignado_por_id' => $jefe->id,
        ]);
    }
}
