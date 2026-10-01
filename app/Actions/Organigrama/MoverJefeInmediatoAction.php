<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mueve a un JEFE INMEDIATO a otra área desde el organigrama (arrastrar y
 * soltar). Como el jefe inmediato es quien encabeza una unidad hoja
 * (`jefe_id`), lo que se mueve es esa unidad: cambia su `parent_id` y se
 * lleva a su jefe y a su gente. UnidadOrganicaObserver recalcula el jefe
 * inmediato y el jefe de área de todos ellos.
 *
 * Reglas, todas validadas aquí en el servidor:
 * - Solo admin.
 * - Debe encabezar exactamente UNA unidad por jefe_id, y esa unidad no
 *   puede tener sub-unidades (quien encabeza una unidad con sub-unidades
 *   es jefe de área: se cambia desde «Depende de» en la unidad).
 * - El área destino debe tener ANTES un jefe de área (jefe_id activo): sin
 *   él, nadie arriba vería las papeletas de esa gente.
 * - El destino debe estar activo, y no ser la propia unidad ni su padre
 *   actual.
 *
 * Si el jefe figuraba en otra unidad, pasa a la suya para que su propio
 * superior sea el jefe del área nueva (ver JefaturaUnidadService).
 *
 * No deja fila en movimientos_organigrama (esa tabla es por trabajador y su
 * "deshacer" valida la unidad de la persona): para revertirlo se vuelve a
 * arrastrar al área anterior.
 */
class MoverJefeInmediatoAction
{
    /**
     * Qué cambiaría, sin guardar nada. Lanza UsuarioException si el
     * movimiento no está permitido.
     *
     * @return array{
     *     jefe: User,
     *     unidad: UnidadOrganica,
     *     origen: ?UnidadOrganica,
     *     destino: UnidadOrganica,
     *     jefe_area: array{antes: ?User, despues: ?User},
     *     personas: int,
     *     avisos: list<string>
     * }
     */
    public function previsualizar(User $actor, User $jefe, UnidadOrganica $destino): array
    {
        $unidad = $this->validar($actor, $jefe, $destino);

        $origen = $unidad->parent_id ? UnidadOrganica::with('jefe')->find($unidad->parent_id) : null;
        $destino->loadMissing('jefe');

        $avisos = [];

        if ((int) $jefe->unidad_organica_id !== (int) $unidad->id) {
            $avisos[] = $jefe->nombre_completo.' figura en otra unidad: pasará a «'.$unidad->nombre.'» para depender del jefe de '.$destino->nombre.'.';
        }

        if (! $destino->hijos()->exists()) {
            $avisos[] = '«'.$destino->nombre.'» todavía no tiene sub-unidades: con este movimiento pasa a ser un área.';
        }

        return [
            'jefe' => $jefe,
            'unidad' => $unidad,
            'origen' => $origen,
            'destino' => $destino,
            'jefe_area' => ['antes' => $origen?->jefe, 'despues' => $destino->jefe],
            'personas' => $unidad->miembros()->where('activo', true)->count(),
            'avisos' => $avisos,
        ];
    }

    /**
     * Ejecuta el movimiento. $confirmado es obligatorio y sin default: no se
     * mueve a nadie sin pasar por la confirmación.
     *
     * $origenEsperadoId es el área en la que la pantalla vio a la unidad
     * (0 = unidad raíz); si entretanto cambió, se aborta.
     *
     * @throws UsuarioException
     */
    public function ejecutar(User $actor, int $jefeId, int $destinoId, int $origenEsperadoId, bool $confirmado): UnidadOrganica
    {
        if (! $confirmado) {
            throw new UsuarioException('Falta confirmar explícitamente el movimiento.');
        }

        return DB::transaction(function () use ($actor, $jefeId, $destinoId, $origenEsperadoId) {
            $jefe = User::query()->lockForUpdate()->find($jefeId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::query()->lockForUpdate()->find($destinoId)
                ?? throw new UsuarioException('El área de destino ya no existe.');

            $unidad = $this->validar($actor, $jefe, $destino);

            if ((int) $unidad->parent_id !== $origenEsperadoId) {
                throw new UsuarioException('La unidad ya no está donde la arrastraste. Actualiza la pantalla e inténtalo de nuevo.');
            }

            // El observer de la unidad recalcula jefe inmediato y de área de todo su subárbol.
            $unidad->parent_id = $destino->id;
            $unidad->save();

            app(JefaturaUnidadService::class)->ubicarJefe($unidad->fresh());

            return $unidad->fresh();
        });
    }

    /**
     * Única puerta de reglas: previsualizar() y ejecutar() pasan por aquí.
     *
     * @return UnidadOrganica la unidad que encabeza $jefe (la que se mueve)
     *
     * @throws UsuarioException
     */
    private function validar(User $actor, User $jefe, UnidadOrganica $destino): UnidadOrganica
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede mover jefes inmediatos entre áreas.');
        }

        $unidades = $jefe->unidadesQueEncabeza()->get();

        if ($unidades->isEmpty()) {
            throw new UsuarioException($jefe->nombre_completo.' no encabeza ninguna unidad: se mueve como trabajador.');
        }

        if ($unidades->count() > 1) {
            throw new UsuarioException($jefe->nombre_completo.' encabeza más de una unidad: mueve cada unidad desde «Depende de» en su ficha.');
        }

        /** @var UnidadOrganica $unidad */
        $unidad = $unidades->first();

        if ($unidad->hijos()->exists()) {
            throw new UsuarioException($jefe->nombre_completo.' es jefe de área («'.$unidad->nombre.'» tiene sub-unidades): cambia «Depende de» desde la ficha de la unidad.');
        }

        if ((int) $destino->id === (int) $unidad->id) {
            throw new UsuarioException('No puedes mover una unidad dentro de sí misma.');
        }

        if ((int) $destino->id === (int) $unidad->parent_id) {
            throw new UsuarioException('«'.$unidad->nombre.'» ya depende de «'.$destino->nombre.'».');
        }

        if (! $destino->activo) {
            throw new UsuarioException('El área de destino está desactivada.');
        }

        if (! $destino->jefe?->activo) {
            throw new UsuarioException(
                '«'.$destino->nombre.'» no tiene jefe de área activo: asígnale uno antes de moverle un jefe inmediato.'
            );
        }

        return $unidad;
    }
}
