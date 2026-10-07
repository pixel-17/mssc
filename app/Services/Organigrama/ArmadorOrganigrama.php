<?php

namespace App\Services\Organigrama;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Models\ConfiguracionTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lado de LECTURA del organigrama: consultas, alcance de quien mira,
 * filtros (búsqueda, sede, inactivos), nodos del árbol, ficha e historial.
 * No modifica nada: las ediciones viven en App\Actions\Organigrama y el
 * componente Livewire solo orquesta (estado de UI + confirmaciones).
 *
 * Todo se carga en unas pocas queries y el árbol se arma en memoria (el
 * organigrama es chico, igual que en UnidadOrganica::descendantIds()).
 */
class ArmadorOrganigrama
{
    /** Estados de papeleta que siguen esperando una respuesta. */
    public const ESTADOS_PENDIENTES = [
        'pendiente_jefe' => 'Pendiente del jefe',
        'observada_por_jefe' => 'Observada por el jefe',
        'pendiente_rrhh' => 'Pendiente de RRHH',
        'observada_por_rrhh' => 'Observada por RRHH',
    ];

    public function __construct(
        private readonly string $buscar = '',
        private readonly string $sede = '',
        private readonly bool $verInactivos = false,
    ) {}

    /**
     * Arma todo lo que pinta el árbol para quien mira: nodos raíz ya
     * filtrados por búsqueda/sede, estadísticas y conteo por sede.
     *
     * @return array{raices: Collection<int, array<string, mixed>>, stats: array<string, int>, conteoSedes: array<string, int>, unidades: Collection<int, UnidadOrganica>, enAlcance: array<int, true>}
     */
    public function armar(User $usuario): array
    {
        // Las unidades (oficinas) son pocas: se traen todas, pero SOLO con
        // jefesTurno (lo que necesitan raicesPara y la ficha). Los jefes con
        // su sede y los trabajadores —el volumen grande— se cargan en
        // cargarRama() únicamente para la rama que quien mira puede ver.
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
            // Closure con `use (&...)` y NO `fn`: las arrow functions capturan por
            // valor, así que $stats y $conteoSedes (acumuladores por referencia de
            // construirNodo) se quedaban en 0 y las tarjetas y el selector de sedes
            // del organigrama mostraban siempre cero.
            ->map(function (UnidadOrganica $u) use ($porPadre, &$stats, &$conteoSedes, $configurados) {
                return $this->construirNodo($u, $porPadre, $stats, $conteoSedes, $configurados);
            })
            ->filter()
            ->values();

        return compact('raices', 'stats', 'conteoSedes', 'unidades', 'enAlcance');
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
    public function cargarRama(Collection $unidades, array $enAlcance): void
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
    public function turnosConfiguradosDeJefes(Collection $unidades, array $enAlcance): array
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
    public function fichaDe(?int $personaId, Collection $unidades, array $enAlcance): ?array
    {
        if ($personaId === null) {
            return null;
        }

        $persona = User::with(['sede', 'unidadOrganica.padre', 'jefeInmediato', 'jefeArea', 'jefesInmediatosAdicionales'])
            ->find($personaId);

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
    public function idsEnAlcance(Collection $raicesDeAlcance, Collection $porPadre): array
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
     * Últimos movimientos visibles para quien mira (admin: todos; Jefe
     * de Área: los que tocan alguna unidad de su alcance).
     *
     * @param  array<int, true>  $enAlcance
     * @return Collection<int, array{movimiento: MovimientoOrganigrama, puede_deshacer: bool}>
     */
    public function historialDe(User $usuario, array $enAlcance, bool $modoEdicion): Collection
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
            'puede_deshacer' => $modoEdicion && $mover->puedeDeshacer($usuario, $m),
        ]);
    }

    /**
     * Unidades desde donde se empieza a pintar.
     *
     * @return Collection<int, UnidadOrganica>
     */
    public function raicesPara(User $usuario, Collection $unidades, Collection $porPadre): Collection
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

        // Por referencia a propósito (ver armar()): con `fn` se perderían los
        // conteos de toda la rama hija.
        $hijos = $porPadre->get($unidad->id, collect())
            ->map(function (UnidadOrganica $h) use ($porPadre, &$stats, &$conteoSedes, $configurados) {
                return $this->construirNodo($h, $porPadre, $stats, $conteoSedes, $configurados);
            })
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
