<?php

namespace App\Livewire\Organigrama;

use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vista de SOLO LECTURA del organigrama como árbol: unidades → jefe
 * inmediato → trabajadores, con la sede de cada persona, para ver de un
 * vistazo quién está ligado a quién.
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

    public function mount(): void
    {
        $this->autorizar();
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

        $unidades = UnidadOrganica::query()
            ->with([
                'jefe.sede',
                'jefesTurno.jefe.sede',
                'miembros' => fn ($q) => $q->with(['sede', 'jefesInmediatosAdicionales'])->orderBy('name')->orderBy('apellido'),
            ])
            ->orderBy('nombre')
            ->get();

        $porPadre = $unidades->groupBy('parent_id');

        $stats = ['unidades' => 0, 'personas' => 0, 'sin_sede' => 0, 'turnos_sin_jefe' => 0];

        $raices = $this->raicesPara($usuario, $unidades, $porPadre)
            ->map(fn (UnidadOrganica $u) => $this->construirNodo($u, $porPadre, $stats))
            ->filter()
            ->values();

        return view('livewire.organigrama.organigrama-arbol', [
            'raices' => $raices,
            'stats' => $stats,
            'esAdmin' => $usuario->hasRole('admin'),
            'etiquetasTurno' => UnidadOrganicaForm::TURNOS,
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
     * filtrado por la búsqueda. null = ese nodo y todo lo de abajo
     * quedan fuera del resultado.
     *
     * @return array{unidad: UnidadOrganica, jefes: Collection<int, User>, miembros: Collection<int, User>, hijos: Collection<int, array<string, mixed>>, total: int, turnos_sin_jefe: list<string>}|null
     */
    private function construirNodo(UnidadOrganica $unidad, Collection $porPadre, array &$stats): ?array
    {
        $q = mb_strtolower(trim($this->buscar));

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

        $miembros = $unidad->miembros
            ->reject(fn (User $m) => $jefes->contains('id', $m->id))
            ->when(! $this->verInactivos, fn (Collection $c) => $c->where('activo', true))
            ->when($q !== '' && ! $coincideUnidad, fn (Collection $c) => $c->filter(
                fn (User $m) => $contiene($m->nombre_completo) || $contiene($m->dni)
            ))
            ->values();

        $hijos = $porPadre->get($unidad->id, collect())
            ->map(fn (UnidadOrganica $h) => $this->construirNodo($h, $porPadre, $stats))
            ->filter()
            ->values();

        if ($q !== '' && ! $coincideUnidad && $miembros->isEmpty() && $hijos->isEmpty()) {
            return null;
        }

        $turnosSinJefe = $unidad->turnosSinJefe();

        $stats['unidades']++;
        $stats['personas'] += $miembros->count() + $jefes->count();
        $stats['sin_sede'] += $miembros->whereNull('sede_id')->count() + $jefes->whereNull('sede_id')->count();
        $stats['turnos_sin_jefe'] += count($turnosSinJefe);

        return [
            'unidad' => $unidad,
            'jefes' => $jefes,
            'miembros' => $miembros,
            'hijos' => $hijos,
            'total' => $miembros->count() + $jefes->count() + $hijos->sum('total'),
            'turnos_sin_jefe' => $turnosSinJefe,
        ];
    }
}
