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
 * Jefes inmediatos adicionales (jefes_turno, ver Avance 00.59, 00.68 y
 * 00.73): asignación manual de QUIÉN es Jefe Inmediato adicional de
 * esta unidad para régimen 728, uno o VARIOS — ya NO se elige a mano
 * un turno fijo (MANANA/TARDE/NOCHE) para cada uno; el turno que cada
 * uno cubre sale siempre de su propia programación de calendario
 * (configuraciones_turno). CrearPapeletaAction guarda a todos los
 * candidatos resueltos (los que hoy están de servicio en el turno del
 * trabajador) y "el que actúa primero decide" (ver
 * UnidadOrganica::resolverJefesInmediatos() y Papeleta::jefesCandidatos()).
 * Solo aplica editando una unidad ya creada (jefes_turno exige
 * unidad_organica_id).
 *
 * Cada jefe agregado aquí necesita, además, su propio ciclo de turno
 * (configuraciones_turno) para que el sistema sepa qué turno cubre y
 * si está "de servicio" hoy — este formulario no lo carga, enlaza a la
 * pantalla dedicada (turnos.configuracion, ver ConfiguracionTurnoForm)
 * que ya reutiliza GeneradorTurnoMensualService::cargarConfiguracion.
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

    /**
     * Lista de jefe_id, jefes inmediatos adicionales de esta unidad
     * (régimen 728) — sin turno: el que cada uno cubre sale de su
     * propia programación de calendario, no de una selección aquí. Un
     * slot en null es una fila añadida en el formulario y aún sin
     * elegir.
     *
     * @var list<int|null>
     */
    public array $jefesAdicionales = [];

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

    /**
     * Etiquetas de los códigos de turno rotativo (MANANA/TARDE/NOCHE).
     * Ya NO se usa para elegir un turno fijo al asignar un jefe
     * inmediato adicional (ver $jefesAdicionales) — solo queda como
     * mapa código -> etiqueta para los avisos de "turnos sin jefe"
     * (ver UsuarioController y unidad-organica-index.blade.php).
     */
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

            $this->jefesAdicionales = $unidad->jefesTurno->pluck('jefe_id')->all();
        }
    }

    public function agregarJefeAdicional(): void
    {
        $this->jefesAdicionales[] = null;
    }

    public function quitarJefeAdicional(int $indice): void
    {
        unset($this->jefesAdicionales[$indice]);
        $this->jefesAdicionales = array_values($this->jefesAdicionales);
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(self::TIPOS))],
            'parentId' => ['nullable', 'exists:unidad_organicas,id'],
            'jefeId' => ['nullable', 'exists:users,id'],
            'activo' => ['boolean'],
            'jefesAdicionales.*' => ['nullable', 'exists:users,id'],
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
                return; // Crear primero; los jefes adicionales se asignan editando.
            }

            // Slots sin elegir (fila añadida y dejada en blanco) y
            // duplicados (mismo jefe elegido dos veces) se descartan
            // aquí; el unique de BD es (unidad, jefe_id).
            $idsDeseados = collect($datos['jefesAdicionales'])->filter()->unique()->values();

            $idsActuales = JefeTurno::where('unidad_organica_id', $unidad->id)->pluck('jefe_id');

            JefeTurno::where('unidad_organica_id', $unidad->id)
                ->whereIn('jefe_id', $idsActuales->diff($idsDeseados))
                ->delete();

            foreach ($idsDeseados->diff($idsActuales) as $jefeId) {
                JefeTurno::create([
                    'unidad_organica_id' => $unidad->id,
                    'jefe_id' => $jefeId,
                ]);
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
