<?php

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\MoverJefeComoTrabajadorAction;
use App\Actions\Organigrama\MoverJefeInmediatoAction;
use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\MovimientoOrganigrama;
use App\Models\Papeleta;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Organigrama como árbol: unidades → jefe inmediato → trabajadores, con la
 * sede de cada persona, para ver de un vistazo quién está ligado a quién.
 *
 * - Filtro por sede (`$sede`): '' = todas, 'sin' = sin sede, o el id de una sede.
 * - Panel lateral (`$personaId`): ficha de la persona. Solo se abre para
 *   gente que cae dentro del alcance de quien mira (se revalida en cada render).
 * - Modo edición (`$modoEdicion`): interruptor que habilita arrastrar un
 *   trabajador a otra unidad. Soltarlo NO mueve nada: abre una
 *   confirmación con lo que cambia (`$propuesta`). Todas las reglas las
 *   valida MoverTrabajadorAction en el servidor; aquí solo se orquesta.
 * - Un jefe inmediato (admin) se arrastra sobre su nueva área: se mueve su
 *   unidad con su gente (MoverJefeInmediatoAction), también con confirmación.
 * - Historial: los últimos movimientos (alcance de quien mira), con
 *   opción de deshacer.
 *
 * - Admin: todo el organigrama (desde las unidades raíz).
 * - Jefe de Área (encabeza una unidad que tiene sub-unidades): su unidad y todo lo que
 *   cuelga de ella. Si encabeza varias, solo se pintan las de más
 *   arriba (las demás ya salen dentro).
 * - Cualquier otro (incluido el Jefe Inmediato de una unidad hoja): 403. La autorización se repite en render() porque
 *   las llamadas de Livewire no re-evalúan el middleware de la ruta.
 *
 * Capa fina: el armado del árbol (consultas, alcance, filtros, ficha e
 * historial) vive en ArmadorOrganigrama; las reglas y ediciones, en
 * App\Actions\Organigrama (reglas compartidas: ReglasOrganigrama).
 */
#[Layout('layouts.app')]
#[Title('Organigrama del área')]
class OrganigramaArbol extends Component
{
    public string $buscar = '';

    public bool $verInactivos = false;

    /** '' = todas las sedes, 'sin' = personas sin sede, o el id de una sede. */
    public string $sede = '';

    public ?int $personaId = null;

    public bool $modoEdicion = false;

    /**
     * Unidad elegida en «Mover a otra unidad» de la ficha (alternativa al
     * arrastre, que no funciona en táctil ni con teclado). Viene del
     * navegador: solo se usa como id para proponerMovimiento(), que lo
     * revalida entero.
     */
    public ?int $destinoMover = null;

    /**
     * Movimiento pendiente de confirmar, o null. Solo guarda ids; viene
     * del navegador, así que MoverTrabajadorAction lo revalida entero al
     * confirmar y la vista previa se recalcula en cada render.
     *
     * `cambiar_sede` (aceptar la sede del jefe del destino) y
     * `quitar_adicionales` son las dos decisiones de la ventana de
     * confirmación; son solo booleanos: la sede concreta la decide el
     * servidor.
     *
     * @var array{trabajador: int, origen: int, destino: int, cambiar_sede: bool, quitar_adicionales: bool}|null
     */
    public ?array $propuesta = null;

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

    public ?string $mensajeOk = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        $this->autorizar();
    }

    public function updatedSede(): void
    {
        // El valor viene del navegador: solo se aceptan los que el selector ofrece.
        if ($this->sede !== '' && $this->sede !== 'sin' && ! ctype_digit($this->sede)) {
            $this->sede = '';
        }
    }

    public function limpiarSede(): void
    {
        $this->sede = '';
    }

    public function alternarEdicion(): void
    {
        $this->autorizar();

        $this->modoEdicion = ! $this->modoEdicion;
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;
    }

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

        // Soltarlo en su misma unidad no es un movimiento como trabajador; el admin sí puede
        // ascenderlo a jefe inmediato de esa unidad.
        if ((int) $trabajador->unidad_organica_id === (int) $destino->id) {
            if ($usuario->hasRole('admin')) {
                $this->proponerMovimientoJefe($usuario, $trabajador, $destino);
            }

            return;
        }

        try {
            $vista = app(MoverTrabajadorAction::class)->previsualizar($usuario, $trabajador, $destino);
        } catch (UsuarioException $e) {
            // Como trabajador no entra (p. ej. la unidad no tiene jefe), pero el admin aún puede ponerlo de jefe.
            if ($usuario->hasRole('admin')) {
                $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

                if ($this->propuestaJefe !== null) {
                    $this->mensajeError = null;

                    return;
                }
            }

            $this->mensajeError = $e->getMessage();

            return;
        }

        $this->propuesta = [
            'trabajador' => (int) $trabajador->id,
            'origen' => (int) $trabajador->unidad_organica_id,
            'destino' => (int) $destino->id,
            // La sede del nuevo jefe se propone ya marcada; quitar jefes adicionales, no.
            'cambiar_sede' => $vista['sede']['propone_cambio'],
            'quitar_adicionales' => false,
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

    public function cancelarMovimiento(): void
    {
        $this->propuesta = null;
        $this->propuestaJefe = null;
    }

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

        $persona = User::find((int) ($propuesta['jefe'] ?? 0));
        $eraJefe = $persona && ($persona->unidadesQueEncabeza()->exists() || $persona->turnosQueEncabeza()->exists());

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
                    (int) ($propuesta['jefe'] ?? 0),
                    (int) ($propuesta['destino'] ?? 0),
                    (int) ($propuesta['origen'] ?? 0),
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
                (int) ($propuesta['jefe'] ?? 0),
                (int) ($propuesta['destino'] ?? 0),
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
                (bool) ($propuesta['quitar_adicionales'] ?? false),
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

    /** Un modal de edición (unidad/trabajador) guardó algo: se repinta el árbol y se muestra el aviso. */
    #[On('org-actualizado')]
    public function alActualizar(?string $mensaje = null): void
    {
        $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeError = null;

        if ($mensaje !== null) {
            $this->mensajeOk = $mensaje;
        }
    }

    public function verPersona(int $id): void
    {
        $this->personaId = $id;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    public function cerrarPersona(): void
    {
        $this->personaId = null;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    /**
     * «Mover a otra unidad» desde la ficha: misma confirmación que al soltar
     * una persona sobre una unidad (proponerMovimiento), pero sin arrastrar.
     * Se cierra la ficha para que la ventana de confirmación no quede
     * apilada encima de ella.
     */
    public function moverDesdeFicha(): void
    {
        $this->autorizar();

        $personaId = $this->personaId;
        $destinoId = $this->destinoMover;

        if (! $this->modoEdicion || $personaId === null) {
            return;
        }

        if ($destinoId === null) {
            $this->addError('destinoMover', 'Elige la unidad de destino.');

            return;
        }

        $this->cerrarPersona();
        $this->proponerMovimiento($personaId, $destinoId);
    }

    private function autorizar(): User
    {
        /** @var User|null $usuario */
        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeArea()),
            403,
        );

        return $usuario;
    }

    public function render(): View
    {
        $usuario = $this->autorizar();

        $armador = new ArmadorOrganigrama($this->buscar, $this->sede, $this->verInactivos);
        ['raices' => $raices, 'stats' => $stats, 'conteoSedes' => $conteoSedes, 'unidades' => $unidades, 'enAlcance' => $enAlcance] = $armador->armar($usuario);

        $ficha = $armador->fichaDe($this->personaId, $unidades, $enAlcance);

        return view('livewire.organigrama.organigrama-arbol', [
            'raices' => $raices,
            'stats' => $stats,
            'esAdmin' => $usuario->hasRole('admin'),
            'etiquetasTurno' => UnidadOrganicaForm::TURNOS,
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
            'conteoSedes' => $conteoSedes,
            'ficha' => $ficha,
            'destinosMover' => $this->destinosParaMover($ficha, $unidades, $enAlcance),
            'movimiento' => $this->vistaPreviaMovimiento($usuario),
            'movimientoJefe' => $this->vistaPreviaMovimientoJefe($usuario),
            'historial' => $armador->historialDe($usuario, $enAlcance, $this->modoEdicion),
        ]);
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

        $jefe = User::find((int) ($this->propuestaJefe['jefe'] ?? 0));
        $destino = UnidadOrganica::find((int) ($this->propuestaJefe['destino'] ?? 0));

        if (! $jefe || ! $destino) {
            return null;
        }

        $vista = ['jefe' => $jefe, 'es_jefe' => $jefe->unidadesQueEncabeza()->exists() || $jefe->turnosQueEncabeza()->exists(), 'destino' => $destino, 'trabajador' => null, 'como_jefe' => null, 'unidad' => null, 'errores' => []];

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
