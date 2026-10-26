<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

use App\Actions\Organigrama\MoverJefeComoTrabajadorAction;
use App\Actions\Organigrama\MoverJefeInmediatoAction;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;

/**
 * Parte de OrganigramaArbol: mover a un JEFE (inmediato o de área) a otra
 * unidad — a diferencia de un trabajador raso, tiene tres formas posibles
 * (entrar como trabajador, como jefe inmediato, o mover su unidad entera
 * con su gente) y la ventana de confirmación deja elegir cuál.
 *
 * Extraído de OrganigramaArbol únicamente para que el archivo principal no
 * crezca sin límite: sigue siendo la misma clase en tiempo de ejecución
 * (un trait se "aplana" dentro de la clase que lo usa), así que accede
 * sin problema a $propuesta, autorizar() y al resto de OrganigramaArbol.
 */
trait MueveJefe
{
    /**
     * Movimiento de un JEFE INMEDIATO a otra área, pendiente de confirmar, o
     * null. Solo ids (vienen del navegador): MoverJefeInmediatoAction lo
     * revalida entero. `origen` = padre actual de su unidad (0 = raíz).
     *
     * @var array{jefe: int, origen: int, destino: int}|null
     */
    public ?array $propuestaJefe = null;

    /**
     * Cómo se mueve un jefe soltado sobre otra unidad. '' = todavía no eligió
     * (no se mueve nada hasta que elija): 'trabajador' (entra como trabajador),
     * 'jefe' (entra como jefe inmediato de esa unidad) o 'unidad' (se mueve su
     * unidad entera con su gente). Viene del navegador: se revalida.
     */
    public string $modoJefe = '';

    /** Jefe actual del destino al que releva el que llega (solo si el destino no tiene lugar libre). */
    public string|int|null $reemplazaDestino = null;

    /**
     * Reemplazo elegido por unidad que deja el jefe: unidad_id => user_id.
     * Lo revalida MoverJefeComoTrabajadorAction.
     *
     * @var array<int|string, int|string|null>
     */
    public array $reemplazos = [];

    /**
     * Turno del jefe 728 al pasar a trabajador (los jefes no tienen turno).
     *
     * @var array{turno: ?string, fecha_ancla: ?string, dias_trabajo: int|string, dias_descanso: int|string}
     */
    public array $turnoJefe = ['turno' => null, 'fecha_ancla' => null, 'dias_trabajo' => 6, 'dias_descanso' => 1];

    /**
     * Se soltó a un jefe (inmediato o de área) sobre una unidad: valida y pide
     * confirmación. No mueve nada. La ventana deja elegir cómo moverlo (como
     * trabajador, como jefe inmediato de esa unidad, o su unidad entera) y
     * solo ofrece las formas que las reglas permiten.
     */
    private function proponerMovimientoJefe(User $usuario, User $jefe, UnidadOrganica $destino): void
    {
        $errores = [];
        $posibles = [];
        $unidad = null;

        foreach ([MoverJefeComoTrabajadorAction::COMO_TRABAJADOR, MoverJefeComoTrabajadorAction::COMO_JEFE] as $modo) {
            try {
                app(MoverJefeComoTrabajadorAction::class)->previsualizar($usuario, $jefe, $destino, $modo);
                $posibles[] = $modo;
            } catch (UsuarioException $e) {
                $errores[] = $e->getMessage();
            }
        }

        try {
            $unidad = app(MoverJefeInmediatoAction::class)->previsualizar($usuario, $jefe, $destino);
            $posibles[] = 'unidad';
        } catch (UsuarioException $e) {
            $errores[] = $e->getMessage();
        }

        if ($posibles === []) {
            $this->mensajeError = implode(' ', array_unique($errores));

            return;
        }

        // Nunca se elige por él: solo si hay una única forma posible queda marcada.
        $this->modoJefe = count($posibles) === 1 ? $posibles[0] : '';
        $this->reemplazos = [];
        $this->reemplazaDestino = null;
        $this->turnoJefe = ['turno' => null, 'fecha_ancla' => now()->toDateString(), 'dias_trabajo' => 6, 'dias_descanso' => 1];

        $this->propuestaJefe = [
            'jefe' => (int) $jefe->id,
            'origen' => (int) ($unidad !== null
                ? $unidad['unidad']->parent_id
                : ($jefe->unidadesQueEncabeza()->value('parent_id') ?? 0)),
            'destino' => (int) $destino->id,
        ];
    }

    public function confirmarMovimientoJefe(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuestaJefe;
        $modo = $this->modoJefe;
        $reemplazos = $this->reemplazos;
        $reemplazaDestino = ($this->reemplazaDestino !== null && $this->reemplazaDestino !== '') ? (int) $this->reemplazaDestino : null;
        $turno = $this->turnoJefe;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        $persona = User::find((int) $propuesta['jefe']);
        $eraJefe = $persona && ($persona->unidadesQueEncabeza()->exists());

        $this->mensajeOk = $this->mensajeError = null;

        if (! in_array($modo, ['trabajador', 'jefe', 'unidad'], true)) {
            $this->mensajeError = 'Elige cómo quieres mover a la persona.';

            return;
        }

        $this->propuesta = null;
        $this->propuestaJefe = null;

        if ($modo === 'unidad') {
            try {
                $unidad = app(MoverJefeInmediatoAction::class)->ejecutar(
                    $usuario,
                    (int) $propuesta['jefe'],
                    (int) $propuesta['destino'],
                    (int) $propuesta['origen'],
                    true,
                );
            } catch (UsuarioException $e) {
                $this->mensajeError = $e->getMessage();

                return;
            }

            $unidad->load(['jefe', 'padre']);
            $this->mensajeOk = '«'.$unidad->nombre.'» ('.($unidad->jefe?->nombre_completo ?? 'sin jefe').') ahora depende de '
                .($unidad->padre?->nombre ?? '—').'.';

            return;
        }

        try {
            $movimiento = app(MoverJefeComoTrabajadorAction::class)->ejecutar(
                $usuario,
                (int) $propuesta['jefe'],
                (int) $propuesta['destino'],
                $reemplazos,
                $turno,
                true,
                $modo,
                $reemplazaDestino,
            );
        } catch (UsuarioException $e) {
            // Se vuelve a mostrar la ventana con lo que ya había elegido, para corregirlo.
            $this->propuestaJefe = $propuesta;
            $this->mensajeError = $e->getMessage();

            return;
        }

        $this->reemplazos = [];
        $this->reemplazaDestino = null;
        $movimiento->load(['trabajador', 'unidadAnterior', 'unidadNueva']);
        $this->mensajeOk = $movimiento->trabajador?->nombre_completo
            .($modo === 'jefe' ? ' pasó a ser jefe inmediato de ' : ' pasó a ser trabajador de ')
            .($movimiento->unidadNueva?->nombre ?? '—')
            .($eraJefe ? '; su unidad anterior conserva a su gente.' : '.');
    }

    /**
     * Datos de la ventana de confirmación del movimiento de un jefe
     * inmediato, o null (se recalcula en cada render; si dejó de ser
     * válido, no se muestra).
     *
     * @return array<string, mixed>|null
     */
    private function vistaPreviaMovimientoJefe(User $usuario): ?array
    {
        if ($this->propuestaJefe === null) {
            return null;
        }

        $jefe = User::find((int) $this->propuestaJefe['jefe']);
        $destino = UnidadOrganica::find((int) $this->propuestaJefe['destino']);

        if (! $jefe || ! $destino) {
            return null;
        }

        $vista = ['jefe' => $jefe, 'es_jefe' => $jefe->unidadesQueEncabeza()->exists(), 'destino' => $destino, 'trabajador' => null, 'como_jefe' => null, 'unidad' => null, 'errores' => []];

        foreach (['trabajador' => MoverJefeComoTrabajadorAction::COMO_TRABAJADOR, 'como_jefe' => MoverJefeComoTrabajadorAction::COMO_JEFE] as $clave => $modo) {
            try {
                $vista[$clave] = app(MoverJefeComoTrabajadorAction::class)->previsualizar($usuario, $jefe, $destino, $modo);
            } catch (UsuarioException $e) {
                $vista['errores'][$clave] = $e->getMessage();
            }
        }

        try {
            $vista['unidad'] = app(MoverJefeInmediatoAction::class)->previsualizar($usuario, $jefe, $destino);
        } catch (UsuarioException $e) {
            $vista['errores']['unidad'] = $e->getMessage();
        }

        return $vista['trabajador'] === null && $vista['como_jefe'] === null && $vista['unidad'] === null ? null : $vista;
    }
}
