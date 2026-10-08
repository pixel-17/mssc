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
 *
 * Cambiar el jefe o el padre pasa por JefaturaUnidadService::validar()
 * (jefe activo, régimen compatible, destino activo). Con $ubicarJefe el
 * jefe nuevo pasa a pertenecer a la unidad, para que su propio superior
 * sea el de la unidad padre y no el de la unidad donde estaba antes.
 */
class GuardarUnidadOrganicaAction
{
    /**
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     * @param  list<int|null>|null  $jefesAdicionales  null = no tocar los existentes
     * @param  bool  $ubicarJefe  pasar al jefe nuevo a esta unidad si estaba en otra
     *
     * @throws UsuarioException
     */
    public function ejecutar(User $actor, ?UnidadOrganica $unidad, array $datos, ?array $jefesAdicionales = null, bool $ubicarJefe = false): UnidadOrganica
    {
        if (! $actor->hasRole('admin')) {
            $this->exigirAlcanceDeJefeDeArea($actor, $unidad, $datos, $jefesAdicionales);
        }

        $jefaturas = app(JefaturaUnidadService::class);
        $jefaturas->validar(
            $unidad,
            isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null,
            isset($datos['parent_id']) ? (int) $datos['parent_id'] : null,
            $ubicarJefe,
        );

        return DB::transaction(function () use ($unidad, $datos, $jefesAdicionales, $ubicarJefe, $jefaturas) {
            $this->validarSinCiclos($unidad, $datos['parent_id'] ?? null);

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

            if ($ubicarJefe) {
                $jefaturas->ubicarJefe($unidad->fresh());
            }

            return $unidad;
        });
    }

    /**
     * Un jefe de área solo puede cambiar QUIÉN jefatura una unidad de su área (nunca la que él
     * encabeza) y el jefe nuevo debe pertenecer a esa misma área. Nombre, tipo, padre, estado y
     * jefes de turno quedan como están: eso sigue siendo del administrador.
     *
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     * @param  list<int|null>|null  $jefesAdicionales
     *
     * @throws UsuarioException
     */
    private function exigirAlcanceDeJefeDeArea(User $actor, ?UnidadOrganica $unidad, array $datos, ?array $jefesAdicionales): void
    {
        $reglas = app(ReglasOrganigrama::class);

        if ($unidad === null || ! $actor->esJefeDeArea() || ! $reglas->puedeGestionarJefaturasEn($actor, $unidad)) {
            throw new UsuarioException('Solo un administrador puede editar esta unidad orgánica.');
        }

        $soloJefatura = $jefesAdicionales === null
            && $datos['nombre'] === $unidad->nombre
            && ($datos['tipo'] ?? null) === $unidad->tipo
            && (int) ($datos['parent_id'] ?? 0) === (int) $unidad->parent_id
            && (bool) $datos['activo'] === (bool) $unidad->activo;

        if (! $soloJefatura) {
            throw new UsuarioException('Como jefe de área solo puedes cambiar quién jefatura la unidad.');
        }

        $jefeId = isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null;

        if ($jefeId !== null && $jefeId !== (int) $unidad->jefe_id) {
            $unidadNuevo = User::find($jefeId)?->unidadOrganica;

            if ($unidadNuevo === null || $reglas->areaDe($actor, $unidadNuevo) !== $reglas->areaDe($actor, $unidad)) {
                throw new UsuarioException('El nuevo jefe debe pertenecer a tu área.');
            }
        }
    }

    /**
     * Sin ciclos: ni su propio padre ni el de un descendiente suyo.
     *
     * Va dentro de la transacción y bloquea el árbol (solo id y parent_id,
     * son pocas filas) antes de recorrerlo: así dos admins moviendo
     * unidades a la vez se serializan y el segundo ve el árbol ya
     * modificado por el primero, en vez de validar contra un estado viejo.
     *
     * @throws UsuarioException
     */
    private function validarSinCiclos(?UnidadOrganica $unidad, mixed $parentId): void
    {
        if (! $unidad || ! $parentId) {
            return;
        }

        UnidadOrganica::query()->select('id', 'parent_id')->lockForUpdate()->get();

        $prohibidos = [$unidad->id, ...$unidad->descendantIds()];

        if (in_array((int) $parentId, $prohibidos, true)) {
            throw new UsuarioException('Esa unidad no puede ser su propio padre ni el de un descendiente suyo.');
        }
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
