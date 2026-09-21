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
    /**
     * A quién puede agregarse un jefe como trabajador de su equipo. Un jefe
     * de área o un administrador NO se puede añadir al equipo de otro jefe
     * (quien lo vinculara pasaría a ver y decidir sobre sus papeletas); un
     * usuario desactivado tampoco tiene sentido en un equipo.
     */
    public function esVinculable(User $trabajador): bool
    {
        return $trabajador->activo
            && ! $trabajador->hasRole('admin')
            && ! $trabajador->esJefeDeArea();
    }

    /**
     * Devuelve null tanto si el DNI no existe como si la persona no es
     * vinculable: así la búsqueda no sirve para averiguar quién es
     * administrador o jefe de área.
     */
    public function buscarPorDni(string $dni): ?User
    {
        $usuario = User::where('dni', $dni)->first();

        return $usuario && $this->esVinculable($usuario) ? $usuario : null;
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
        if ($jefe->id === $trabajador->id) {
            throw new UsuarioException('No puedes agregarte a ti mismo como jefe inmediato.');
        }

        if (! $this->esVinculable($trabajador)) {
            throw new UsuarioException('No puedes agregar a tu equipo a un jefe de área, ni a un administrador, ni a un usuario desactivado.');
        }

        // Consulta directa (sin pasar por User::esJefeInmediatoDe(), que
        // memoiza por instancia): aquí mismo vamos a modificar esa
        // relación con el attach() de abajo, así que cachear su
        // resultado dejaría, dentro de este mismo request, un "false"
        // obsoleto para cualquiera que vuelva a preguntar por $jefe.
        $yaEsAdicional = $trabajador->jefesInmediatosAdicionales()
            ->where('users.id', $jefe->id)
            ->exists();

        if ($trabajador->jefe_inmediato_id === $jefe->id || $yaEsAdicional) {
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
