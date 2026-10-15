<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Models\MovimientoOrganigrama;
use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Support\Collection;

/**
 * Parte de OrganigramaArbol: mover a un trabajador raso (no jefe) de una
 * unidad a otra, y el historial de esos movimientos (deshacer incluido).
 *
 * Extraído de OrganigramaArbol únicamente para que el archivo principal no
 * crezca sin límite: sigue siendo la misma clase en tiempo de ejecución
 * (un trait se "aplana" dentro de la clase que lo usa), así que accede
 * sin problema a autorizar(), proponerMovimientoJefe() (del trait
 * MueveJefe) y al resto de OrganigramaArbol.
 */
trait MueveTrabajador
{
    /**
     * Movimiento pendiente de confirmar, o null. Solo guarda ids; viene
     * del navegador, así que MoverTrabajadorAction lo revalida entero al
     * confirmar y la vista previa se recalcula en cada render.
     *
     * `cambiar_sede` (aceptar la sede del jefe del destino) es la decisión
     * de la ventana de confirmación; es solo un booleano: la sede concreta
     * la decide el servidor.
     *
     * @var array{trabajador: int, origen: int, destino: int, cambiar_sede: bool}|null
     */
    public ?array $propuesta = null;

    /** Se soltó a $trabajadorId sobre la unidad $destinoId: valida y pide confirmación. No mueve nada. */
    public function proponerMovimiento(int $trabajadorId, int $destinoId): void
    {
        $usuario = $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion) {
            return;
        }

        $trabajador = User::find($trabajadorId);
        $destino = UnidadOrganica::find($destinoId);

        if (! $trabajador || ! $destino) {
            $this->mensajeError = 'No se encontró a la persona o la unidad.';

            return;
        }

        // Quien encabeza una unidad es un jefe inmediato: otro flujo (se mueve su unidad entera).
        if ($trabajador->unidadesQueEncabeza()->exists()) {
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            return;
        }

        // Soltarlo en su misma unidad no es un movimiento como trabajador: se puede
        // ascenderlo a jefe inmediato de esa unidad (el servidor valida el alcance).
        if ((int) $trabajador->unidad_organica_id === (int) $destino->id) {
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            return;
        }

        try {
            $vista = app(MoverTrabajadorAction::class)->previsualizar($usuario, $trabajador, $destino);
        } catch (UsuarioException $e) {
            // Como trabajador no entra (p. ej. la unidad no tiene jefe), pero aún se puede ponerlo de jefe.
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            if ($this->propuestaJefe !== null) {
                $this->mensajeError = null;

                return;
            }

            $this->mensajeError = $e->getMessage();

            return;
        }

        $this->propuesta = [
            'trabajador' => (int) $trabajador->id,
            'origen' => (int) $trabajador->unidad_organica_id,
            'destino' => (int) $destino->id,
            // La sede del nuevo jefe se propone ya marcada.
            'cambiar_sede' => $vista['sede']['propone_cambio'],
        ];
    }

    /** Desde la ventana de un trabajador: en vez de moverlo como trabajador, ponerlo de jefe inmediato del destino. */
    public function ascenderTrabajador(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuesta;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        $trabajador = User::find((int) ($propuesta['trabajador'] ?? 0));
        $destino = UnidadOrganica::find((int) ($propuesta['destino'] ?? 0));

        if (! $trabajador || ! $destino) {
            return;
        }

        $this->mensajeOk = $this->mensajeError = null;
        $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

        if ($this->propuestaJefe !== null) {
            $this->propuesta = null;
        }
    }

    public function confirmarMovimiento(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuesta;
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        try {
            $movimiento = app(MoverTrabajadorAction::class)->ejecutar(
                $usuario,
                (int) ($propuesta['trabajador'] ?? 0),
                (int) ($propuesta['destino'] ?? 0),
                (int) ($propuesta['origen'] ?? 0),
                true,
                (bool) ($propuesta['cambiar_sede'] ?? false),
            );
        } catch (UsuarioException $e) {
            $this->mensajeError = $e->getMessage();

            return;
        }

        $movimiento->load(['trabajador', 'unidadAnterior', 'unidadNueva']);
        $this->mensajeOk = $movimiento->trabajador?->nombre_completo.' pasó de '
            .($movimiento->unidadAnterior?->nombre ?? '—').' a '.($movimiento->unidadNueva?->nombre ?? '—').'.';
    }

    public function deshacerMovimiento(int $movimientoId): void
    {
        $usuario = $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion) {
            return;
        }

        $movimiento = MovimientoOrganigrama::find($movimientoId);

        if (! $movimiento) {
            $this->mensajeError = 'El movimiento ya no existe.';

            return;
        }

        try {
            $reversion = app(MoverTrabajadorAction::class)->deshacer($usuario, $movimiento);
        } catch (UsuarioException $e) {
            $this->mensajeError = $e->getMessage();

            return;
        }

        $reversion->load(['trabajador', 'unidadNueva']);
        $this->mensajeOk = 'Se deshizo el movimiento: '.$reversion->trabajador?->nombre_completo
            .' volvió a '.($reversion->unidadNueva?->nombre ?? '—').'.';
    }

    /**
     * Datos de la ventana de confirmación del movimiento propuesto, o
     * null. Se recalcula en cada render con MoverTrabajadorAction (que
     * valida permisos y régimen): si la propuesta dejó de ser válida,
     * simplemente no se muestra.
     *
     * @return array<string, mixed>|null
     */
    private function vistaPreviaMovimiento(User $usuario): ?array
    {
        if ($this->propuesta === null) {
            return null;
        }

        $trabajador = User::find((int) ($this->propuesta['trabajador'] ?? 0));
        $destino = UnidadOrganica::find((int) ($this->propuesta['destino'] ?? 0));

        if (! $trabajador || ! $destino) {
            return null;
        }

        try {
            $vista = app(MoverTrabajadorAction::class)->previsualizar($usuario, $trabajador, $destino);
        } catch (UsuarioException) {
            return null;
        }

        $vista['pendientes'] = Papeleta::query()
            ->where('trabajador_id', $trabajador->id)
            ->whereIn('estado', array_keys(ArmadorOrganigrama::ESTADOS_PENDIENTES))
            ->count();

        return $vista;
    }

    /**
     * Unidades a las que se puede proponer mover a la persona de la ficha:
     * activas, dentro del alcance de quien mira y distintas de la actual.
     * Solo es una lista para elegir; las reglas las valida el servidor.
     *
     * @param  array<string, mixed>|null  $ficha
     * @param  Collection<int, UnidadOrganica>  $unidades
     * @param  array<int, true>  $enAlcance
     * @return list<array{id: int, label: string, hint: string}>
     */
    private function destinosParaMover(?array $ficha, Collection $unidades, array $enAlcance): array
    {
        if (! $this->modoEdicion || $ficha === null) {
            return [];
        }

        $actual = (int) $ficha['persona']->unidad_organica_id;

        return $unidades
            ->filter(fn (UnidadOrganica $u) => isset($enAlcance[$u->id]) && $u->activo && (int) $u->id !== $actual)
            ->map(fn (UnidadOrganica $u) => [
                'id' => (int) $u->id,
                'label' => $u->nombre,
                'hint' => $u->jefe?->nombre_completo ?? 'sin jefe',
            ])
            ->values()
            ->all();
    }
}
