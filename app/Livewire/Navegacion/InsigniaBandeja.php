<?php

namespace App\Livewire\Navegacion;

use App\Models\Papeleta;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Contador de papeletas por decidir que va junto al enlace de la bandeja
 * en el sidebar ('jefe' o 'rrhh').
 *
 * Reemplaza dos COUNT que se ejecutaban en layouts/app.blade.php en cada
 * carga de página (y cuyo filtro comparaba `estado` contra el nombre de la
 * clase, así que dejó de coincidir al pasar los estados a $name: el badge
 * quedaba siempre en 0). Aquí se usa whereState() y el mismo criterio de
 * la bandeja (JefeIndex / RrhhIndex), y el número se actualiza solo con
 * el mismo canal en vivo que las bandejas.
 *
 * No usa EscuchaNotificacionesEnVivo a propósito: ese trait dispara el
 * sonido de notificación y ya lo dispara la campana; con este componente
 * el tono sonaría dos veces.
 */
class InsigniaBandeja extends Component
{
    /** Cliente no puede cambiarla: decide qué cuenta se consulta. */
    #[Locked]
    public string $bandeja = 'jefe';

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = Auth::id();

        return [
            "echo-notification:App.Models.User.{$id}" => '$refresh',
            "echo-private:App.Models.User.{$id},PapeletaActualizada" => '$refresh',
        ];
    }

    public function render(): View
    {
        return view('livewire.navegacion.insignia-bandeja', [
            'cantidad' => $this->contar(),
        ]);
    }

    private function contar(): int
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return 0;
        }

        return match ($this->bandeja) {
            'jefe' => Papeleta::whereState('estado', PendienteJefe::class)
                ->where(fn ($q) => $q->deJefeInmediato($usuario)->orWhere('papeletas.jefe_area_id', $usuario->id))
                ->count(),
            'rrhh' => $usuario->hasRole('rrhh')
                ? Papeleta::whereState('estado', PendienteRrhh::class)->count()
                : 0,
            default => 0,
        };
    }
}
