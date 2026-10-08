<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;

/**
 * Reglas y vista previa de dos cambios sobre una unidad orgánica:
 *
 * - Cambiar QUIÉN la encabeza (`jefe_id`).
 * - Moverla de padre (`parent_id`): el jefe y las personas de la unidad se
 *   van con ella y su jefe de área se recalcula.
 *
 * GuardarUnidadOrganicaAction llama a validar() antes de guardar, así que
 * valen igual para el catálogo y para el organigrama; UnidadModal usa
 * previsualizar() para mostrar de antemano qué cambia, con las mismas
 * funciones de UnidadOrganica que usa UserObserver (no pueden divergir).
 *
 * Reglas:
 * - El jefe nuevo debe existir y estar activo.
 * - Régimen: todos en una unidad comparten el de su jefe (ver
 *   UnidadOrganica::regimen()). Si cambia el jefe, el régimen del nuevo debe
 *   coincidir con el de las personas activas y los jefes de turno que ya
 *   tiene la unidad. Una unidad vacía acepta cualquier régimen.
 * - Si se pide pasar al jefe nuevo a la unidad (ubicarJefe), no puede
 *   encabezar ya otra unidad ni ser jefe de turno en otra: dejaría a esa
 *   otra unidad con un jefe que ya no pertenece a ella.
 * - Una unidad desactivada no recibe sub-unidades.
 */
class JefaturaUnidadService
{
    /** @throws UsuarioException */
    public function validar(?UnidadOrganica $unidad, ?int $jefeId, ?int $parentId, bool $ubicarJefe = false): void
    {
        if ($parentId !== null && ($unidad === null || (int) $unidad->parent_id !== $parentId)) {
            $padre = UnidadOrganica::find($parentId)
                ?? throw new UsuarioException('La unidad de la que depende ya no existe.');

            if (! $padre->activo) {
                throw new UsuarioException('«'.$padre->nombre.'» está desactivada: no puede tener sub-unidades.');
            }
        }

        if ($jefeId === null) {
            return;
        }

        $jefe = User::find($jefeId)
            ?? throw new UsuarioException('El jefe elegido ya no existe.');

        $cambiaJefe = $unidad === null || (int) $unidad->jefe_id !== (int) $jefe->id;

        if (! $cambiaJefe) {
            return;
        }

        if (! $jefe->activo) {
            throw new UsuarioException($jefe->nombre_completo.' está desactivado: no puede encabezar una unidad.');
        }

        if ($unidad !== null && $jefe->regimen !== null) {
            $excluidos = array_filter([(int) $unidad->jefe_id, (int) $jefe->id]);

            $personas = $unidad->miembros()
                ->where('activo', true)
                ->whereNotIn('id', $excluidos)
                ->where('regimen', '!=', $jefe->regimen)
                ->count();

            $jefesDeTurno = $unidad->jefesTurno()
                ->whereHas('jefe', fn ($q) => $q->where('regimen', '!=', $jefe->regimen))
                ->count();

            if ($personas > 0) {
                throw new UsuarioException(
                    'El régimen de '.$jefe->nombre_completo.' ('.$jefe->regimen.') no coincide con el de '
                    .$personas.' '.($personas === 1 ? 'persona' : 'personas').' de la unidad. '
                    .'Todos en una unidad comparten el régimen de su jefe: muévelas primero a otra unidad.'
                );
            }

            if ($jefesDeTurno > 0) {
                throw new UsuarioException(
                    'La unidad tiene jefes de turno de otro régimen que el de '.$jefe->nombre_completo
                    .' ('.$jefe->regimen.'). Quítalos primero.'
                );
            }
        }

        if ($ubicarJefe) {
            $otraUnidad = $jefe->unidadesQueEncabeza()
                ->when($unidad, fn ($q) => $q->whereKeyNot($unidad->id))
                ->first();

            if ($otraUnidad) {
                throw new UsuarioException(
                    $jefe->nombre_completo.' ya encabeza «'.$otraUnidad->nombre.'»: cambia primero esa jefatura '
                    .'o desmarca «Pasarlo a esta unidad».'
                );
            }

            $turnoEnOtra = $jefe->turnosQueEncabeza()
                ->when($unidad, fn ($q) => $q->where('unidad_organica_id', '!=', $unidad->id))
                ->exists();

            if ($turnoEnOtra) {
                throw new UsuarioException(
                    $jefe->nombre_completo.' es jefe de turno en otra unidad: quítalo de allí '
                    .'o desmarca «Pasarlo a esta unidad».'
                );
            }
        }
    }

    /**
     * Qué cambiaría al guardar la unidad con ese jefe y ese padre. null si
     * la unidad todavía no existe (no hay "antes").
     *
     * @return array{
     *     error: ?string,
     *     jefe: array{antes: ?User, despues: ?User, cambia: bool, fuera_de_la_unidad: bool},
     *     padre: array{antes: ?UnidadOrganica, despues: ?UnidadOrganica, cambia: bool},
     *     personas: int,
     *     subarbol: int,
     *     avisos: list<string>
     * }|null
     */
    public function previsualizar(?UnidadOrganica $unidad, ?int $jefeId, ?int $parentId, bool $ubicarJefe = false): ?array
    {
        if ($unidad === null) {
            return null;
        }

        try {
            $this->validar($unidad, $jefeId, $parentId, $ubicarJefe);
            $error = null;
        } catch (UsuarioException $e) {
            $error = $e->getMessage();
        }

        $jefeAntes = $unidad->jefe_id ? User::find($unidad->jefe_id) : null;
        $jefeDespues = $jefeId ? User::find($jefeId) : null;
        $cambiaJefe = (int) $unidad->jefe_id !== (int) $jefeId;

        $padreAntes = $unidad->parent_id ? UnidadOrganica::with('jefe')->find($unidad->parent_id) : null;
        $padreDespues = $parentId ? UnidadOrganica::with('jefe')->find($parentId) : null;
        $cambiaPadre = (int) $unidad->parent_id !== (int) $parentId;

        $personas = $unidad->miembros()->where('activo', true)->count();
        $subarbol = User::whereIn('unidad_organica_id', [$unidad->id, ...$unidad->descendantIds()])
            ->where('activo', true)
            ->count();

        $fueraDeLaUnidad = $cambiaJefe
            && $jefeDespues !== null
            && (int) $jefeDespues->unidad_organica_id !== (int) $unidad->id;

        $avisos = [];

        if ($cambiaJefe && $jefeAntes) {
            $avisos[] = $jefeAntes->nombre_completo.' deja de encabezar la unidad y sigue en ella como trabajador; muévelo si hace falta.';
        }

        if ($cambiaJefe && $jefeAntes) {
            $pendientes = Papeleta::whereState('estado', PendienteJefe::class)
                ->where('jefe_inmediato_id', $jefeAntes->id)
                ->count();

            if ($pendientes > 0) {
                $avisos[] = $pendientes.' '.($pendientes === 1 ? 'papeleta pendiente seguirá' : 'papeletas pendientes seguirán')
                    .' con '.$jefeAntes->nombre_completo.' (cada papeleta guarda a su jefe al crearse; no pasan al jefe nuevo).';
            }
        }

        if ($cambiaJefe && $jefeId === null && $personas > 0) {
            $avisos[] = 'Sin jefe, las papeletas de '.$personas.' '.($personas === 1 ? 'persona' : 'personas').' no tendrán a quién llegar.';
        }

        if ($fueraDeLaUnidad) {
            $suJefe = ($cambiaPadre ? $padreDespues : $padreAntes)?->jefe;
            $avisos[] = $ubicarJefe
                ? $jefeDespues->nombre_completo.' pasará a esta unidad y dependerá de '
                    .($suJefe?->nombre_completo ?? 'nadie (la unidad no tiene unidad padre con jefe)').'.'
                : $jefeDespues->nombre_completo.' seguirá en su unidad actual: su propio jefe no cambia. '
                    .'Marca «Pasarlo a esta unidad» para que dependa del jefe de la unidad padre.';
        }

        if ($cambiaPadre) {
            $avisos[] = 'La unidad se mueve con su jefe y sus personas: se recalculan los jefes de '
                .$subarbol.' '.($subarbol === 1 ? 'persona' : 'personas').' activas.';
        }

        return [
            'error' => $error,
            'jefe' => [
                'antes' => $jefeAntes,
                'despues' => $jefeDespues,
                'cambia' => $cambiaJefe,
                'fuera_de_la_unidad' => $fueraDeLaUnidad,
            ],
            'padre' => ['antes' => $padreAntes, 'despues' => $padreDespues, 'cambia' => $cambiaPadre],
            'personas' => $personas,
            'subarbol' => $subarbol,
            'avisos' => $avisos,
        ];
    }

    /**
     * Pasa al jefe de la unidad a la propia unidad si estaba en otra. Quien
     * encabeza una unidad también es miembro de ella y su superior está un
     * nivel arriba (ver UnidadOrganica::jefaturasDe); si siguiera en su
     * unidad anterior, sus papeletas irían al jefe de ESA unidad.
     * UserObserver recalcula su jefe inmediato y de área al guardar.
     */
    public function ubicarJefe(UnidadOrganica $unidad): void
    {
        $jefe = $unidad->jefe_id ? User::find($unidad->jefe_id) : null;

        if (! $jefe || (int) $jefe->unidad_organica_id === (int) $unidad->id) {
            return;
        }

        $jefe->unidad_organica_id = $unidad->id;
        $jefe->save();
    }
}
