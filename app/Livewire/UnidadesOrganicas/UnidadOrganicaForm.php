<?php

namespace App\Livewire\UnidadesOrganicas;

use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reemplaza a UnidadOrganicaResource::form() de Filament. El jefe_id
 * de esta unidad es el Jefe Inmediato de sus miembros; el jefe_id de
 * la unidad padre es el Jefe de Área (ver UnidadOrganica::jefeInmediato()
 * / jefeArea()). `tipo` es solo decorativo, nunca condiciona el
 * escalamiento de papeletas.
 *
 * Jefes por turno (jefes_turno, ver Avance 00.59): asignación manual y
 * fija del Jefe Inmediato de esta unidad para régimen 728, una por
 * turno (MANANA/TARDE/NOCHE) — CrearPapeletaAction la usa en vez de
 * $jefeId cuando hay fila para el turno vigente del trabajador (ver
 * UnidadOrganica::resolverJefeInmediato()). Solo aplica editando una
 * unidad ya creada (jefes_turno exige unidad_organica_id).
 */
#[Layout('layouts.app')]
#[Title('Unidad orgánica')]
class UnidadOrganicaForm extends Component
{
    use RequiereAdmin;

    #[Locked]
    public ?UnidadOrganica $unidad = null;

    public string $nombre = '';

    public ?string $tipo = null;

    public ?int $parentId = null;

    public ?int $jefeId = null;

    public bool $activo = true;

    /** @var array<string, ?int> turno => jefe_id (null = sin asignar, cae a $jefeId) */
    public array $jefesPorTurno = [
        'MANANA' => null,
        'TARDE' => null,
        'NOCHE' => null,
    ];

    public const TIPOS = [
        'alta_direccion' => 'Alta dirección',
        'consultivo' => 'Consultivo',
        'control' => 'Control',
        'apoyo' => 'Apoyo',
        'apoyo_alcaldia' => 'Apoyo a alcaldía',
        'asesoramiento' => 'Asesoramiento',
        'linea_2do_nivel' => 'Línea - 2do nivel',
        'linea_3er_nivel' => 'Línea - 3er nivel',
    ];

    public const TURNOS = [
        'MANANA' => 'Mañana',
        'TARDE' => 'Tarde',
        'NOCHE' => 'Noche',
    ];

    public function mount(?UnidadOrganica $unidad = null): void
    {
        if ($unidad?->exists) {
            $this->unidad = $unidad;
            $this->nombre = $unidad->nombre;
            $this->tipo = $unidad->tipo;
            $this->parentId = $unidad->parent_id;
            $this->jefeId = $unidad->jefe_id;
            $this->activo = $unidad->activo;

            foreach ($unidad->jefesTurno as $jefeTurno) {
                $this->jefesPorTurno[$jefeTurno->turno] = $jefeTurno->jefe_id;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(self::TIPOS))],
            'parentId' => ['nullable', 'exists:unidad_organicas,id'],
            'jefeId' => ['nullable', 'exists:users,id'],
            'activo' => ['boolean'],
            'jefesPorTurno.*' => ['nullable', 'exists:users,id'],
        ];
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        // No puede ser su propio padre ni el de ninguno de sus
        // descendientes, o el árbol se vuelve cíclico.
        if ($this->unidad && $datos['parentId']) {
            $prohibidos = [$this->unidad->id, ...$this->unidad->descendantIds()];

            if (in_array((int) $datos['parentId'], $prohibidos, true)) {
                $this->addError('parentId', 'Esa unidad no puede ser su propio padre ni el de un descendiente suyo.');

                return;
            }
        }

        $atributos = [
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'parent_id' => $datos['parentId'],
            'jefe_id' => $datos['jefeId'],
            'activo' => $datos['activo'],
        ];

        DB::transaction(function () use ($atributos, $datos) {
            $unidad = $this->unidad
                ? tap($this->unidad)->update($atributos)
                : UnidadOrganica::create($atributos);

            if (! $this->unidad) {
                return; // Crear primero; los jefes por turno se asignan editando.
            }

            foreach ($datos['jefesPorTurno'] as $turno => $jefeId) {
                if ($jefeId) {
                    JefeTurno::updateOrCreate(
                        ['unidad_organica_id' => $unidad->id, 'turno' => $turno],
                        ['jefe_id' => $jefeId]
                    );
                } else {
                    JefeTurno::where('unidad_organica_id', $unidad->id)->where('turno', $turno)->delete();
                }
            }
        });

        session()->flash('mensaje', $this->unidad ? 'Unidad orgánica actualizada.' : 'Unidad orgánica creada.');

        $this->redirectRoute('unidades-organicas.index', navigate: false);
    }

    public function render(): View
    {
        $padresDisponibles = UnidadOrganica::orderBy('nombre')
            ->when($this->unidad, fn ($query) => $query
                ->whereKeyNot($this->unidad->id)
                ->whereNotIn('id', $this->unidad->descendantIds()))
            ->pluck('nombre', 'id');

        return view('livewire.unidades-organicas.unidad-organica-form', [
            'padresDisponibles' => $padresDisponibles,
            'jefesDisponibles' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
