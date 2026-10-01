<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea o edita una unidad orgánica (nombre, tipo, padre, jefe, activo y
 * jefes inmediatos adicionales de turno). Es la única implementación:
 * la usan UnidadOrganicaForm (catálogo) y UnidadModal (organigrama).
 *
 * Solo admin. UnidadOrganicaObserver recalcula jefe_inmediato_id /
 * jefe_area_id del subárbol cuando cambian jefe_id o parent_id.
 */
class GuardarUnidadOrganicaAction
{
    /**
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     * @param  list<int|null>|null  $jefesAdicionales  null = no tocar los existentes
     *
     * @throws UsuarioException
     */
    public function ejecutar(User $actor, ?UnidadOrganica $unidad, array $datos, ?array $jefesAdicionales = null): UnidadOrganica
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede editar unidades orgánicas.');
        }

        // Sin ciclos: ni su propio padre ni el de un descendiente suyo.
        if ($unidad && $datos['parent_id']) {
            $prohibidos = [$unidad->id, ...$unidad->descendantIds()];

            if (in_array((int) $datos['parent_id'], $prohibidos, true)) {
                throw new UsuarioException('Esa unidad no puede ser su propio padre ni el de un descendiente suyo.');
            }
        }

        return DB::transaction(function () use ($unidad, $datos, $jefesAdicionales) {
            $atributos = [
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'parent_id' => $datos['parent_id'],
                'jefe_id' => $datos['jefe_id'],
                'activo' => $datos['activo'],
            ];

            $unidad = $unidad
                ? tap($unidad)->update($atributos)
                : UnidadOrganica::create($atributos);

            if ($jefesAdicionales !== null) {
                $this->sincronizarJefesDeTurno($unidad, $jefesAdicionales);
            }

            return $unidad;
        });
    }

    /** @param  list<int|null>  $jefesAdicionales */
    private function sincronizarJefesDeTurno(UnidadOrganica $unidad, array $jefesAdicionales): void
    {
        // Slots en blanco y duplicados se descartan (unique de BD: unidad + jefe).
        $deseados = collect($jefesAdicionales)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $actuales = JefeTurno::where('unidad_organica_id', $unidad->id)->pluck('jefe_id');

        JefeTurno::where('unidad_organica_id', $unidad->id)
            ->whereIn('jefe_id', $actuales->diff($deseados))
            ->delete();

        foreach ($deseados->diff($actuales) as $jefeId) {
            JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefeId]);
        }
    }
}
