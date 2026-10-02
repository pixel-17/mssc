<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mueve a un trabajador de una unidad orgánica a otra desde el
 * organigrama (arrastrar y soltar) y deja registro en
 * movimientos_organigrama, con opción de deshacerlo.
 *
 * TODAS las reglas se validan aquí, en el servidor; la pantalla solo
 * las refleja. Quien llama (componente Livewire) nunca decide nada:
 *
 * - Permisos: un admin mueve a cualquiera a cualquier unidad. Un Jefe
 *   de Área solo dentro de SU área (origen y destino bajo la misma
 *   unidad que encabeza y que tiene sub-unidades); mover entre áreas
 *   distintas es solo del admin. El resto de usuarios no mueve a nadie.
 * - Régimen: todos los de una unidad comparten el régimen de su jefe
 *   (ver UnidadOrganica::regimen()); no se mueve a un 276 a una unidad
 *   728 ni al revés. Una unidad sin jefe todavía no define régimen.
 * - La unidad destino debe tener un jefe inmediato activo (jefe_id).
 * - Quien encabeza una unidad (jefe_id o jefes_turno) no se mueve
 *   desde aquí: un jefe inmediato se mueve entre áreas con
 *   MoverJefeInmediatoAction; para cambiar la jefatura, desde la unidad.
 *
 * El movimiento cambia `unidad_organica_id`. Los jefes (inmediato y de
 * área) los recalcula UserObserver::saving() con
 * UnidadOrganica::jefaturasDe(), que es la misma función con la que
 * previsualizar() calcula "qué cambia" — así la vista previa y el
 * resultado no pueden divergir.
 *
 * Dos decisiones opcionales de quien confirma (ver ejecutar()):
 * - Sede: se PROPONE la del jefe de la unidad destino (solo si tiene
 *   sede y es distinta). La sede la decide el servidor; quien confirma
 *   solo dice sí o no, nunca manda un id de sede.
 * - Jefes inmediatos adicionales anteriores: conservar (por defecto) o
 *   quitar. Los que se quitan quedan anotados en el movimiento para
 *   devolverlos si se deshace.
 */
class MoverTrabajadorAction
{
    /**
     * Qué cambiaría al mover a $trabajador a $destino, sin guardar
     * nada. Lanza UsuarioException si el movimiento no está permitido,
     * por lo que sirve también como validación previa a pedir
     * confirmación.
     *
     * @return array{
     *     trabajador: User,
     *     origen: UnidadOrganica,
     *     destino: UnidadOrganica,
     *     jefe_inmediato: array{antes: ?User, despues: ?User, cambia: bool},
     *     jefe_area: array{antes: ?User, despues: ?User, cambia: bool},
     *     sede: array{actual: ?Sede, propuesta: ?Sede, propone_cambio: bool},
     *     adicionales: Collection<int, User>,
     *     avisos: list<string>
     * }
     */
    public function previsualizar(User $actor, User $trabajador, UnidadOrganica $destino): array
    {
        $origen = $this->origenDe($trabajador);
        $this->validar($actor, $trabajador, $origen, $destino);

        [$jefeInmediatoAntes, $jefeAreaAntes] = $origen->jefaturasDe($trabajador);
        [$jefeInmediatoDespues, $jefeAreaDespues] = $destino->jefaturasDe($trabajador);

        $usuarios = User::whereIn('id', array_filter([
            $jefeInmediatoAntes, $jefeAreaAntes, $jefeInmediatoDespues, $jefeAreaDespues,
        ]))->get()->keyBy('id');
        $usuarioDe = fn (?int $id): ?User => $id === null ? null : $usuarios->get($id);

        $avisos = [];

        $turno = ConfiguracionTurno::where('user_id', $trabajador->id)->value('turno');
        if (in_array($turno, ConfiguracionTurno::TURNOS_728, true)
            && $destino->resolverJefeInmediato($turno) === null) {
            $avisos[] = 'En '.$destino->nombre.' nadie cubre el turno '
                .ConfiguracionTurno::etiquetaDeTurno($turno).': quedaría sin jefe inmediato para ese turno.';
        }

        $sedePropuestaId = $this->sedePropuestaId($trabajador, $destino);
        $sedeActual = $trabajador->sede_id ? Sede::find($trabajador->sede_id) : null;

        return [
            'trabajador' => $trabajador,
            'origen' => $origen,
            'destino' => $destino,
            'jefe_inmediato' => [
                'antes' => $usuarioDe($jefeInmediatoAntes),
                'despues' => $usuarioDe($jefeInmediatoDespues),
                'cambia' => $jefeInmediatoAntes !== $jefeInmediatoDespues,
            ],
            'jefe_area' => [
                'antes' => $usuarioDe($jefeAreaAntes),
                'despues' => $usuarioDe($jefeAreaDespues),
                'cambia' => $jefeAreaAntes !== $jefeAreaDespues,
            ],
            'sede' => [
                'actual' => $sedeActual,
                'propuesta' => $sedePropuestaId ? Sede::find($sedePropuestaId) : null,
                'propone_cambio' => $sedePropuestaId !== null,
            ],
            'adicionales' => $trabajador->jefesInmediatosAdicionales()->get(),
            'avisos' => $avisos,
        ];
    }

    /**
     * Sede que se propone al mover: la del jefe de la unidad destino, solo
     * si la tiene y es distinta a la actual del trabajador. null = no hay
     * nada que proponer.
     */
    private function sedePropuestaId(User $trabajador, UnidadOrganica $destino): ?int
    {
        $sedeDelJefe = $destino->jefe?->sede_id;

        if ($sedeDelJefe === null || (int) $sedeDelJefe === (int) $trabajador->sede_id) {
            return null;
        }

        return (int) $sedeDelJefe;
    }

    /**
     * Ejecuta el movimiento. $confirmado es obligatorio y sin default
     * (mismo criterio que AsignarJefeAdicionalAction): no se puede
     * mover a nadie sin haber pasado por la confirmación.
     *
     * $origenEsperadoId es la unidad en la que la pantalla vio a la
     * persona; si entretanto cambió (otro admin la movió), se aborta.
     *
     * $cambiarSede: aceptar la sede propuesta (la del jefe del destino).
     * Se ignora si no hay propuesta. $quitarAdicionales: quitarle sus
     * jefes inmediatos adicionales; false los conserva.
     *
     * @throws UsuarioException
     */
    public function ejecutar(
        User $actor,
        int $trabajadorId,
        int $destinoId,
        int $origenEsperadoId,
        bool $confirmado,
        bool $cambiarSede = false,
        bool $quitarAdicionales = false,
    ): MovimientoOrganigrama {
        if (! $confirmado) {
            throw new UsuarioException('Falta confirmar explícitamente el movimiento.');
        }

        return DB::transaction(function () use ($actor, $trabajadorId, $destinoId, $origenEsperadoId, $cambiarSede, $quitarAdicionales) {
            $trabajador = User::query()->lockForUpdate()->find($trabajadorId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::find($destinoId)
                ?? throw new UsuarioException('La unidad de destino ya no existe.');

            if ((int) $trabajador->unidad_organica_id !== $origenEsperadoId) {
                throw new UsuarioException('La persona ya no está en la unidad desde la que la arrastraste. Actualiza la pantalla e inténtalo de nuevo.');
            }

            $this->validar($actor, $trabajador, $this->origenDe($trabajador), $destino);

            return $this->aplicar($actor, $trabajador, $destino, [
                'sede_id' => $cambiarSede ? $this->sedePropuestaId($trabajador, $destino) : null,
                'quitar_adicionales' => $quitarAdicionales,
            ]);
        });
    }

    /**
     * Deshace un movimiento: devuelve a la persona a la unidad anterior
     * y registra una fila nueva (revierte_id) sin borrar la original.
     *
     * Solo se puede si el movimiento no se deshizo ya, no es una
     * reversión, la persona sigue en la unidad a la que se movió, no
     * tiene movimientos más recientes, y quien deshace pasa las mismas
     * reglas de permisos y régimen que un movimiento normal (la unidad
     * anterior pudo cambiar de jefe desde entonces).
     *
     * Los jefes inmediato y de área se recalculan con el organigrama
     * VIGENTE, no se restauran los que tenía: si la jefatura cambió
     * mientras tanto, la persona queda con los jefes actuales de su
     * unidad de antes. Sí se restaura lo que el movimiento cambió por
     * decisión de quien confirmó: la sede anterior (si se había cambiado)
     * y los jefes adicionales que se quitaron.
     *
     * @throws UsuarioException
     */
    public function deshacer(User $actor, MovimientoOrganigrama $movimiento): MovimientoOrganigrama
    {
        return DB::transaction(function () use ($actor, $movimiento) {
            $original = MovimientoOrganigrama::query()->lockForUpdate()->find($movimiento->id)
                ?? throw new UsuarioException('El movimiento ya no existe.');
            $trabajador = $original->trabajador_id
                ? User::query()->lockForUpdate()->find($original->trabajador_id)
                : null;

            [, $destino] = $this->validarDeshacer($actor, $original, $trabajador);

            $reversion = $this->aplicar($actor, $trabajador, $destino, [
                'sede_id' => $original->cambioSede() ? $original->sede_anterior_id : null,
                'restaurar_adicionales' => $original->jefes_adicionales_quitados ?? [],
                'revierte' => $original,
            ]);

            $original->forceFill([
                'deshecho_at' => now(),
                'deshecho_por_id' => $actor->id,
            ])->save();

            return $reversion;
        });
    }

    /** ¿Se vería permitido deshacer este movimiento ahora mismo? (para mostrar u ocultar el botón; deshacer() revalida) */
    public function puedeDeshacer(User $actor, MovimientoOrganigrama $movimiento): bool
    {
        try {
            $this->validarDeshacer($actor, $movimiento, $movimiento->trabajador_id ? User::find($movimiento->trabajador_id) : null);

            return true;
        } catch (UsuarioException) {
            return false;
        }
    }

    /**
     * @return array{0: UnidadOrganica, 1: UnidadOrganica} [unidad actual, unidad a la que vuelve]
     */
    private function validarDeshacer(User $actor, MovimientoOrganigrama $movimiento, ?User $trabajador): array
    {
        if ($movimiento->deshecho_at !== null) {
            throw new UsuarioException('Este movimiento ya se deshizo.');
        }

        if ($movimiento->revierte_id !== null) {
            throw new UsuarioException('Una reversión no se puede deshacer; si hace falta, vuelve a mover a la persona.');
        }

        if (! $trabajador) {
            throw new UsuarioException('La persona ya no existe.');
        }

        $hayMasRecientes = MovimientoOrganigrama::where('trabajador_id', $movimiento->trabajador_id)
            ->where('id', '>', $movimiento->id)
            ->exists();
        if ($hayMasRecientes) {
            throw new UsuarioException('Esta persona tiene movimientos más recientes; deshaz primero el último.');
        }

        if ((int) $trabajador->unidad_organica_id !== (int) $movimiento->unidad_nueva_id) {
            throw new UsuarioException('La persona ya no está en la unidad a la que se movió.');
        }

        $actual = UnidadOrganica::find($movimiento->unidad_nueva_id)
            ?? throw new UsuarioException('La unidad a la que se movió ya no existe.');
        $anterior = UnidadOrganica::find($movimiento->unidad_anterior_id)
            ?? throw new UsuarioException('La unidad de origen ya no existe.');

        $this->validar($actor, $trabajador, $actual, $anterior);

        return [$actual, $anterior];
    }

    /**
     * Nadie entra a una unidad que no tiene jefe inmediato (activo): sus
     * papeletas no tendrían a quién llegar. Vale también para el admin.
     *
     * @throws UsuarioException
     */
    public function exigirJefeInmediato(UnidadOrganica $destino): void
    {
        if (! $destino->jefe?->activo) {
            throw new UsuarioException(
                'La unidad «'.$destino->nombre.'» no tiene un jefe inmediato activo: '
                .'asígnaselo en la unidad antes de moverle personas.'
            );
        }
    }

    private function origenDe(User $trabajador): UnidadOrganica
    {
        return $trabajador->unidadOrganica
            ?? throw new UsuarioException('Esa persona no pertenece a ninguna unidad orgánica.');
    }

    /**
     * Reglas de negocio y de permisos. Única puerta: previsualizar(),
     * ejecutar() y deshacer() pasan por aquí.
     *
     * @throws UsuarioException
     */
    private function validar(User $actor, User $trabajador, UnidadOrganica $origen, UnidadOrganica $destino): void
    {
        $esAdmin = $actor->hasRole('admin');

        if (! $esAdmin && ! $actor->esJefeDeArea()) {
            throw new UsuarioException('No tienes permiso para mover personas en el organigrama.');
        }

        if ((int) $origen->id === (int) $destino->id) {
            throw new UsuarioException('La persona ya está en esa unidad.');
        }

        if (! $destino->activo) {
            throw new UsuarioException('La unidad de destino está desactivada.');
        }

        $this->exigirJefeInmediato($destino);

        if (! $esAdmin) {
            if ($trabajador->hasRole('admin')) {
                throw new UsuarioException('No puedes mover a un administrador.');
            }

            if ((int) $trabajador->id === (int) $actor->id) {
                throw new UsuarioException('No puedes moverte a ti mismo.');
            }
        }

        if ($trabajador->unidadesQueEncabeza()->exists() || $trabajador->turnosQueEncabeza()->exists()) {
            throw new UsuarioException('Quien encabeza una unidad no se mueve desde el organigrama: cambia primero su jefatura en la unidad.');
        }

        app(ReglasOrganigrama::class)->exigirRegimenCompatible($trabajador, $destino);

        if (! $esAdmin) {
            $areaOrigen = app(ReglasOrganigrama::class)->areaDe($actor, $origen);

            if ($areaOrigen === null) {
                throw new UsuarioException('Esa persona está fuera de tu área.');
            }

            if ($areaOrigen !== app(ReglasOrganigrama::class)->areaDe($actor, $destino)) {
                throw new UsuarioException('Solo un administrador puede mover personas entre áreas.');
            }
        }
    }

    /**
     * @param  array{sede_id?: ?int, quitar_adicionales?: bool, restaurar_adicionales?: list<array{jefe_id: int, asignado_por_id: ?int}>, revierte?: ?MovimientoOrganigrama}  $opciones
     */
    private function aplicar(User $actor, User $trabajador, UnidadOrganica $destino, array $opciones = []): MovimientoOrganigrama
    {
        $antes = [
            'unidad' => $trabajador->unidad_organica_id,
            'sede' => $trabajador->sede_id,
            'jefe_inmediato' => $trabajador->jefe_inmediato_id,
            'jefe_area' => $trabajador->jefe_area_id,
        ];

        $quitados = null;
        if (! empty($opciones['quitar_adicionales'])) {
            $quitados = $trabajador->jefesInmediatosAdicionales()->get()
                ->map(fn (User $j) => ['jefe_id' => (int) $j->id, 'asignado_por_id' => $j->pivot->asignado_por_id])
                ->values()
                ->all();
            $trabajador->jefesInmediatosAdicionales()->detach();
        }

        // UserObserver::saving() recalcula jefe_inmediato_id / jefe_area_id;
        // UserObserver::saved() resincroniza los turnos futuros si cambia la sede.
        $trabajador->unidad_organica_id = $destino->id;
        if (! empty($opciones['sede_id'])) {
            $trabajador->sede_id = $opciones['sede_id'];
        }
        $trabajador->save();

        foreach ($opciones['restaurar_adicionales'] ?? [] as $fila) {
            $jefeId = (int) ($fila['jefe_id'] ?? 0);

            // Fuera: él mismo, uno que ya no existe, o quien ahora ya es su jefe inmediato por la unidad.
            if ($jefeId === (int) $trabajador->id
                || $jefeId === (int) $trabajador->jefe_inmediato_id
                || ! User::whereKey($jefeId)->exists()) {
                continue;
            }

            $trabajador->jefesInmediatosAdicionales()->syncWithoutDetaching([
                $jefeId => ['asignado_por_id' => User::whereKey($fila['asignado_por_id'] ?? 0)->exists() ? $fila['asignado_por_id'] : null],
            ]);
        }

        $trabajador->refresh();

        return MovimientoOrganigrama::create([
            'trabajador_id' => $trabajador->id,
            'actor_id' => $actor->id,
            'unidad_anterior_id' => $antes['unidad'],
            'unidad_nueva_id' => $trabajador->unidad_organica_id,
            'sede_anterior_id' => $antes['sede'],
            'sede_nueva_id' => $trabajador->sede_id,
            'jefe_inmediato_anterior_id' => $antes['jefe_inmediato'],
            'jefe_inmediato_nuevo_id' => $trabajador->jefe_inmediato_id,
            'jefe_area_anterior_id' => $antes['jefe_area'],
            'jefe_area_nuevo_id' => $trabajador->jefe_area_id,
            'jefes_adicionales_quitados' => $quitados,
            'revierte_id' => ($opciones['revierte'] ?? null)?->id,
        ]);
    }
}
