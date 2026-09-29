<?php

namespace App\Livewire\Turnos;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Programación de turnos para el Admin, pensada para muchos usuarios:
 * en vez de listar a todos, se BUSCA a la persona (nombre, apellido,
 * DNI o correo) y el resultado dice si es Trabajador, Jefe inmediato o
 * Jefe de área.
 *
 * - Trabajador 728: va a su calendario día por día (turnos.programacion).
 * - Trabajador 276: va a la carga de su ciclo (turnos.configuracion).
 * - Jefe: se abre debajo la misma grilla de equipo que ve ese jefe
 *   (CalendarioEquipoIndex con $jefeId), así el admin programa a todo
 *   su equipo igual que lo haría él. Lo guardado queda a nombre del admin.
 *
 * Solo admin: la ruta lleva role:admin y las acciones lo revalidan
 * (ver RequiereAdmin).
 */
#[Layout('layouts.app')]
#[Title('Programar horarios — administración')]
class ProgramacionAdmin extends Component
{
    use RequiereAdmin;

    private const MAX_RESULTADOS = 15;

    public string $buscar = '';

    /** Jefe cuyo equipo se está programando. Locked: solo cambia por seleccionarJefe(). */
    #[Locked]
    public ?int $jefeId = null;

    public function mount(): void
    {
        $this->autorizarAdmin();
    }

    public function seleccionarJefe(int $id, EquipoDelJefeService $equipos): void
    {
        $this->autorizarAdmin();

        $jefe = User::findOrFail($id);

        if (! $equipos->idsDeJefes()->contains($jefe->id)) {
            session()->flash('error', $jefe->nombre_completo.' no es jefe de nadie: búscalo como trabajador.');

            return;
        }

        $this->jefeId = $jefe->id;
    }

    public function quitarJefe(): void
    {
        $this->autorizarAdmin();

        $this->jefeId = null;
    }

    public function render(EquipoDelJefeService $equipos): View
    {
        $this->autorizarAdmin();

        $palabras = preg_split('/\s+/', trim($this->buscar), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $resultados = collect();

        // Menos de 2 letras devolvería casi a todos los usuarios: no se busca.
        if ($palabras !== [] && mb_strlen(trim($this->buscar)) >= 2) {
            $consulta = User::with('unidadOrganica')->orderBy('name')->orderBy('apellido');

            // Cada palabra debe aparecer en algún campo: "juan perez" encuentra
            // a Juan (name) Perez (apellido) sin depender de CONCAT del motor.
            foreach ($palabras as $palabra) {
                $consulta->where(fn ($q) => $q
                    ->where('name', 'like', "%{$palabra}%")
                    ->orWhere('apellido', 'like', "%{$palabra}%")
                    ->orWhere('dni', 'like', "%{$palabra}%")
                    ->orWhere('email', 'like', "%{$palabra}%"));
            }

            $resultados = $consulta->limit(self::MAX_RESULTADOS + 1)->get();
        }

        return view('livewire.turnos.programacion-admin', [
            'resultados' => $resultados->take(self::MAX_RESULTADOS),
            'hayMas' => $resultados->count() > self::MAX_RESULTADOS,
            'idsJefes' => $equipos->idsDeJefes()->all(),
            // Jefe de área = encabeza una unidad que tiene sub-unidades debajo.
            // Quien encabeza una unidad sin sub-unidades es Jefe inmediato de su gente.
            'idsJefesDeArea' => UnidadOrganica::whereNotNull('jefe_id')->whereHas('hijos')->pluck('jefe_id')->map(fn ($id) => (int) $id)->all(),
            'jefe' => $this->jefeId ? User::find($this->jefeId) : null,
        ]);
    }
}
