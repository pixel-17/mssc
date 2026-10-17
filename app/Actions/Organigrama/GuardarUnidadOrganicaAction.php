<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea o edita una unidad orgánica (nombre, tipo, padre, jefe y activo). Es la única implementación:
 * la usan UnidadOrganicaForm (catálogo) y UnidadModal (organigrama).
 *
 * Admin: todo. Jefe de Área: además de cambiar jefaturas en su área (ver
 * exigirAlcanceDeJefeDeAreaAlEditar), puede crear una sub-unidad nueva
 * ("una rama más") dentro de su propia área (ver
 * exigirAlcanceDeJefeDeAreaAlCrear) — nunca editar una ya existente más
 * allá de su jefatura, ni crear una unidad raíz. UnidadOrganicaObserver
 * recalcula jefe_inmediato_id / jefe_area_id del subárbol cuando cambian
 * jefe_id o parent_id.
 *
 * Cambiar el jefe o el padre pasa por JefaturaUnidadService::validar()
 * (jefe activo, destino activo). Con $ubicarJefe el
 * jefe nuevo pasa a pertenecer a la unidad, para que su propio superior
 * sea el de la unidad padre y no el de la unidad donde estaba antes.
 */
class GuardarUnidadOrganicaAction
{
    /**
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     * @param  bool  $ubicarJefe  pasar al jefe nuevo a esta unidad si estaba en otra
     *
     * @throws UsuarioException
     */
    public function ejecutar(User $actor, ?UnidadOrganica $unidad, array $datos, bool $ubicarJefe = false): UnidadOrganica
    {
        if (! $actor->hasRole('admin')) {
            $this->exigirAlcanceDeJefeDeArea($actor, $unidad, $datos);
        }

        $jefaturas = app(JefaturaUnidadService::class);
        $jefaturas->validar(
            $unidad,
            isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null,
            isset($datos['parent_id']) ? (int) $datos['parent_id'] : null,
            $ubicarJefe,
        );

        return DB::transaction(function () use ($unidad, $datos, $ubicarJefe, $jefaturas) {
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

            if ($ubicarJefe) {
                $jefaturas->ubicarJefe($unidad->fresh());
            }

            return $unidad;
        });
    }

    /**
     * Delega en el alcance que corresponda: crear una sub-unidad nueva
     * (exigirAlcanceDeJefeDeAreaAlCrear) o editar la jefatura de una que ya
     * existe (exigirAlcanceDeJefeDeAreaAlEditar).
     *
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     *
     * @throws UsuarioException
     */
    private function exigirAlcanceDeJefeDeArea(User $actor, ?UnidadOrganica $unidad, array $datos): void
    {
        if ($unidad === null) {
            $this->exigirAlcanceDeJefeDeAreaAlCrear($actor, $datos);

            return;
        }

        $this->exigirAlcanceDeJefeDeAreaAlEditar($actor, $unidad, $datos);
    }

    /**
     * Un jefe de área puede crear una sub-unidad nueva ("una rama más") dentro
     * de su propia área: la unidad que encabeza o cualquiera de sus
     * sub-unidades, nunca fuera de ella ni como unidad raíz. Si de entrada le
     * asigna un jefe, ese jefe también debe pertenecer a su área.
     *
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     *
     * @throws UsuarioException
     */
    private function exigirAlcanceDeJefeDeAreaAlCrear(User $actor, array $datos): void
    {
        $reglas = app(ReglasOrganigrama::class);

        $parentId = isset($datos['parent_id']) ? (int) $datos['parent_id'] : null;

        if (! $actor->esJefeDeArea() || $parentId === null || ! $reglas->esJefeDeAreaDe($actor, $parentId)) {
            throw new UsuarioException('Solo puedes crear sub-unidades dentro de tu propia área.');
        }

        $jefeId = isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null;

        if ($jefeId !== null) {
            $padre = UnidadOrganica::find($parentId);
            $unidadDelJefeNuevo = User::find($jefeId)?->unidadOrganica;

            if ($padre === null || $unidadDelJefeNuevo === null
                || $reglas->areaDe($actor, $unidadDelJefeNuevo) !== $reglas->areaDe($actor, $padre)) {
                throw new UsuarioException('El jefe que asignes debe pertenecer a tu área.');
            }
        }
    }

    /**
     * Un jefe de área solo puede cambiar QUIÉN jefatura una unidad de su área (nunca la que él
     * encabeza) y el jefe nuevo debe pertenecer a esa misma área. Nombre, tipo, padre y estado
     * quedan como están: eso sigue siendo del administrador.
     *
     * @param  array{nombre:string,tipo:?string,parent_id:?int,jefe_id:?int,activo:bool}  $datos
     *
     * @throws UsuarioException
     */
    private function exigirAlcanceDeJefeDeAreaAlEditar(User $actor, UnidadOrganica $unidad, array $datos): void
    {
        $reglas = app(ReglasOrganigrama::class);

        if (! $actor->esJefeDeArea() || ! $reglas->puedeGestionarJefaturasEn($actor, $unidad)) {
            throw new UsuarioException('Solo un administrador puede editar esta unidad orgánica.');
        }

        $soloJefatura = $datos['nombre'] === $unidad->nombre
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
}
