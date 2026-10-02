<?php

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\MoverJefeComoTrabajadorAction;
use App\Actions\Organigrama\MoverJefeInmediatoAction;
use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\ConfiguracionTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\Papeleta;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
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
 * Todo se carga en unas pocas queries y el árbol se arma en memoria
 * (el organigrama es chico, igual que en UnidadOrganica::descendantIds()).
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

    /** Estados de papeleta que siguen esperando una respuesta. */
    private const ESTADOS_PENDIENTES = [
        'pendiente_jefe' => 'Pendiente del jefe',
        'observada_por_jefe' => 'Observada por el jefe',
        'pendiente_rrhh' => 'Pendiente de RRHH',
        'observada_por_rrhh' => 'Observada por RRHH',
    ];

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
    }

    public function cerrarPersona(): void
    {
        $this->personaId = null;
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

        // Las unidades (oficinas) son pocas: se traen todas, pero SOLO con
        // jefesTurno (lo que necesitan raicesPara y la ficha). Los jefes con
        // su sede y los trabajadores —el volumen grande— se cargan más abajo
        // únicamente para la rama que quien mira puede ver.
        $unidades = UnidadOrganica::query()
            ->with('jefesTurno')
            ->orderBy('nombre')
            ->get();

        $porPadre = $unidades->groupBy('parent_id');

        $stats = ['unidades' => 0, 'personas' => 0, 'sin_sede' => 0, 'turnos_sin_jefe' => 0];
        // Personas por sede ('sin' = sin sede) con la búsqueda ya aplicada pero
        // SIN el filtro de sede, para que el selector muestre cuántos hay en cada una.
        $conteoSedes = [];

        $raicesDeAlcance = $this->raicesPara($usuario, $unidades, $porPadre);
        $enAlcance = $this->idsEnAlcance($raicesDeAlcance, $porPadre);

        $this->cargarRama($unidades, $enAlcance);
        $configurados = $this->turnosConfiguradosDeJefes($unidades, $enAlcance);

        $raices = $raicesDeAlcance
            ->map(fn (UnidadOrganica $u) => $this->construirNodo($u, $porPadre, $stats, $conteoSedes, $configurados))
            ->filter()
            ->values();

        return view('livewire.organigrama.organigrama-arbol', [
            'raices' => $raices,
            'stats' => $stats,
            'esAdmin' => $usuario->hasRole('admin'),
            'etiquetasTurno' => UnidadOrganicaForm::TURNOS,
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
            'conteoSedes' => $conteoSedes,
            'ficha' => $this->fichaDe($unidades, $enAlcance),
            'movimiento' => $this->vistaPreviaMovimiento($usuario),
            'movimientoJefe' => $this->vistaPreviaMovimientoJefe($usuario),
            'historial' => $this->historialDe($usuario, $enAlcance),
        ]);
    }

    /**
     * Carga jefes (con sede), jefes de turno y trabajadores SOLO de las
     * unidades dentro del alcance de quien mira. Un Jefe de Área ya no
     * trae a todos los trabajadores de la municipalidad para pintar su
     * rama. Las relaciones quedan en las mismas instancias que usa
     * $porPadre, así que construirNodo() las ve sin más queries.
     *
     * @param  array<int, true>  $enAlcance
     */
    private function cargarRama(Collection $unidades, array $enAlcance): void
    {
        $unidades
            ->filter(fn (UnidadOrganica $u) => isset($enAlcance[$u->id]))
            ->loadMissing([
                'jefe.sede',
                'jefesTurno.jefe.sede',
                'miembros' => fn ($q) => $q
                    // Los inactivos solo se piden si el interruptor está activo
                    // (construirNodo también los descarta en memoria; es el mismo criterio).
                    ->when(! $this->verInactivos, fn ($q) => $q->where('activo', true))
                    ->with(['sede', 'jefesInmediatosAdicionales'])
                    ->orderBy('name')
                    ->orderBy('apellido'),
            ]);
    }

    /**
     * Turnos configurados de los jefes de la rama, en UNA query, para que
     * turnosSinJefe() no consulte `configuraciones_turno` por cada unidad
     * y turno en cada interacción de Livewire.
     *
     * @param  array<int, true>  $enAlcance
     * @return array<string, array<int, true>>
     */
    private function turnosConfiguradosDeJefes(Collection $unidades, array $enAlcance): array
    {
        $jefeIds = $unidades
            ->filter(fn (UnidadOrganica $u) => isset($enAlcance[$u->id]))
            ->flatMap(fn (UnidadOrganica $u) => [$u->jefe_id, ...$u->jefesTurno->pluck('jefe_id')]);

        return ConfiguracionTurno::idsPorTurno($jefeIds);
    }

    /**
     * Ficha de la persona abierta en el panel lateral, o null. Solo se
     * arma si la persona cae dentro del alcance de quien mira (admin:
     * todo; Jefe de Área: su rama): $personaId viene del navegador y no
     * se confía en él.
     *
     * @param  array<int, true>  $enAlcance  ids de unidades dentro del alcance (ver idsEnAlcance)
     * @return array<string, mixed>|null
     */
    private function fichaDe(Collection $unidades, array $enAlcance): ?array
    {
        if ($this->personaId === null) {
            return null;
        }

        $persona = User::with(['sede', 'unidadOrganica.padre', 'jefeInmediato', 'jefeArea', 'jefesInmediatosAdicionales'])
            ->find($this->personaId);

        if (! $persona) {
            return null;
        }

        $dentro = isset($enAlcance[$persona->unidad_organica_id])
            || $unidades->contains(fn (UnidadOrganica $u) => isset($enAlcance[$u->id])
                && $u->esJefeDeLaUnidad($persona));

        if (! $dentro) {
            return null;
        }

        $adicionalesIds = $persona->jefesInmediatosAdicionales->pluck('id');

        $porEstado = Papeleta::query()
            ->where('trabajador_id', $persona->id)
            ->whereIn('estado', array_keys(self::ESTADOS_PENDIENTES))
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $turno = ConfiguracionTurno::where('user_id', $persona->id)->value('turno');

        return [
            'persona' => $persona,
            'encabeza' => $unidades->filter(fn (UnidadOrganica $u) => $u->esJefeDeLaUnidad($persona))->pluck('nombre')->values(),
            'jefes' => $persona->jefesInmediatos()->map(fn (User $j) => [
                'nombre' => $j->nombre_completo,
                'adicional' => $adicionalesIds->contains($j->id),
            ]),
            'turno' => $turno ? ConfiguracionTurno::etiquetaDeTurno($turno) : null,
            'pendientes' => collect(self::ESTADOS_PENDIENTES)
                ->map(fn (string $etiqueta, string $estado) => ['etiqueta' => $etiqueta, 'total' => (int) ($porEstado[$estado] ?? 0)])
                ->filter(fn (array $fila) => $fila['total'] > 0)
                ->values(),
            'pendientes_total' => (int) $porEstado->sum(),
        ];
    }

    /**
     * Ids de todas las unidades dentro del alcance de quien mira: las
     * raíces y todo lo que cuelga de ellas.
     *
     * @return array<int, true>
     */
    private function idsEnAlcance(Collection $raicesDeAlcance, Collection $porPadre): array
    {
        $enAlcance = [];
        $pendientes = $raicesDeAlcance->pluck('id')->all();

        while ($pendientes) {
            $id = array_pop($pendientes);

            // Ya visitada: evita el bucle infinito si hubiera un ciclo en BD.
            if (isset($enAlcance[$id])) {
                continue;
            }

            $enAlcance[$id] = true;
            foreach ($porPadre->get($id, []) as $hijo) {
                $pendientes[] = $hijo->id;
            }
        }

        return $enAlcance;
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
            ->whereIn('estado', array_keys(self::ESTADOS_PENDIENTES))
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
     * Últimos movimientos visibles para quien mira (admin: todos; Jefe
     * de Área: los que tocan alguna unidad de su alcance).
     *
     * @param  array<int, true>  $enAlcance
     * @return Collection<int, array{movimiento: MovimientoOrganigrama, puede_deshacer: bool}>
     */
    private function historialDe(User $usuario, array $enAlcance): Collection
    {
        $consulta = MovimientoOrganigrama::query()
            ->with(['trabajador', 'actor', 'unidadAnterior', 'unidadNueva', 'sedeAnterior', 'sedeNueva'])
            ->latest('id')
            ->limit(10);

        if (! $usuario->hasRole('admin')) {
            $ids = array_keys($enAlcance);
            $consulta->where(fn ($q) => $q->whereIn('unidad_anterior_id', $ids)->orWhereIn('unidad_nueva_id', $ids));
        }

        $mover = app(MoverTrabajadorAction::class);

        return $consulta->get()->map(fn (MovimientoOrganigrama $m) => [
            'movimiento' => $m,
            'puede_deshacer' => $this->modoEdicion && $mover->puedeDeshacer($usuario, $m),
        ]);
    }

    /**
     * Unidades desde donde se empieza a pintar.
     *
     * @return Collection<int, UnidadOrganica>
     */
    private function raicesPara(User $usuario, Collection $unidades, Collection $porPadre): Collection
    {
        if ($usuario->hasRole('admin')) {
            return $porPadre->get(null, collect());
        }

        $porId = $unidades->keyBy('id');
        // Jefe de Área = encabeza unidades CON sub-unidades (ver User::unidadesDeArea).
        $encabezadas = $unidades
            ->where('jefe_id', $usuario->id)
            ->filter(fn (UnidadOrganica $u) => $porPadre->has($u->id))
            ->pluck('id')
            ->all();

        // Solo las de más arriba: si encabeza la Oficina y también la
        // Sub Oficina que cuelga de ella, la segunda ya sale dentro.
        return $unidades
            ->whereIn('id', $encabezadas)
            ->filter(function (UnidadOrganica $u) use ($porId, $encabezadas) {
                for ($p = $porId->get($u->parent_id); $p; $p = $porId->get($p->parent_id)) {
                    if (in_array($p->id, $encabezadas, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * Arma el nodo de una unidad (y, recursivamente, sus hijos) ya
     * filtrado por la búsqueda y por la sede. null = ese nodo y todo lo
     * de abajo quedan fuera del resultado.
     *
     * @param  array<string, int>  $conteoSedes
     * @param  array<string, array<int, true>>  $configurados  ver turnosConfiguradosDeJefes()
     * @return array{unidad: UnidadOrganica, jefes: Collection<int, User>, jefes_en_filtro: list<int>, filtro_sede: bool, miembros: Collection<int, User>, hijos: Collection<int, array<string, mixed>>, total: int, turnos_sin_jefe: list<string>}|null
     */
    private function construirNodo(UnidadOrganica $unidad, Collection $porPadre, array &$stats, array &$conteoSedes, array $configurados): ?array
    {
        $q = mb_strtolower(trim($this->buscar));
        $hayFiltroSede = $this->sede !== '';

        $enSede = fn (User $p) => match (true) {
            ! $hayFiltroSede => true,
            $this->sede === 'sin' => $p->sede_id === null,
            default => (int) $p->sede_id === (int) $this->sede,
        };

        // Quienes encabezan (jefe_id + jefes de turno) se pintan como
        // jefes; no se repiten en la lista de trabajadores.
        $jefes = collect([$unidad->jefe])
            ->merge($unidad->jefesTurno->map(fn ($jt) => $jt->jefe))
            ->filter()
            ->unique('id')
            ->values();

        $contiene = fn (?string $texto) => $q !== '' && str_contains(mb_strtolower((string) $texto), $q);

        $coincideUnidad = $q !== '' && (
            $contiene($unidad->nombre)
            || $jefes->contains(fn (User $j) => $contiene($j->nombre_completo) || $contiene($j->dni))
        );

        // Trabajadores tras búsqueda y "desactivados", ANTES del filtro de sede.
        $miembrosBase = $unidad->miembros
            ->reject(fn (User $m) => $jefes->contains('id', $m->id))
            ->when(! $this->verInactivos, fn (Collection $c) => $c->where('activo', true))
            ->when($q !== '' && ! $coincideUnidad, fn (Collection $c) => $c->filter(
                fn (User $m) => $contiene($m->nombre_completo) || $contiene($m->dni)
            ))
            ->values();

        // Conteo por sede para el selector (ignora el filtro de sede a propósito).
        $contados = $miembrosBase->concat(
            $q === '' || $coincideUnidad ? $jefes : $jefes->filter(fn (User $j) => $contiene($j->nombre_completo) || $contiene($j->dni))
        );
        foreach ($contados as $persona) {
            $clave = $persona->sede_id === null ? 'sin' : (string) $persona->sede_id;
            $conteoSedes[$clave] = ($conteoSedes[$clave] ?? 0) + 1;
        }

        $miembros = $miembrosBase->filter($enSede)->values();
        $jefesEnFiltro = $jefes->filter($enSede)->values();

        $hijos = $porPadre->get($unidad->id, collect())
            ->map(fn (UnidadOrganica $h) => $this->construirNodo($h, $porPadre, $stats, $conteoSedes, $configurados))
            ->filter()
            ->values();

        $pasaBusqueda = $q === '' || $coincideUnidad || $miembros->isNotEmpty() || $hijos->isNotEmpty();
        $pasaSede = ! $hayFiltroSede || $miembros->isNotEmpty() || $jefesEnFiltro->isNotEmpty() || $hijos->isNotEmpty();

        if (! $pasaBusqueda || ! $pasaSede) {
            return null;
        }

        $turnosSinJefe = $unidad->turnosSinJefe($configurados);

        $stats['unidades']++;
        $stats['personas'] += $miembros->count() + $jefesEnFiltro->count();
        $stats['sin_sede'] += $miembros->whereNull('sede_id')->count() + $jefesEnFiltro->whereNull('sede_id')->count();
        $stats['turnos_sin_jefe'] += count($turnosSinJefe);

        return [
            'unidad' => $unidad,
            'jefes' => $jefes,
            'jefes_en_filtro' => $jefesEnFiltro->pluck('id')->all(),
            'filtro_sede' => $hayFiltroSede,
            'miembros' => $miembros,
            'hijos' => $hijos,
            'total' => $miembros->count() + $jefesEnFiltro->count() + $hijos->sum('total'),
            'turnos_sin_jefe' => $turnosSinJefe,
        ];
    }
}
