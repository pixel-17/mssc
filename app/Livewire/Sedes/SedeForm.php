<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a SedeResource::form() de Filament para el admin que "no
 * quiere saber nada de Filament". El catálogo de Sedes ahora vive acá,
 * en Blade + Livewire puro (ver rutas 'sedes.*' en routes/web.php,
 * middleware role:admin).
 *
 * La ubicación (latitud/longitud) se marca EXCLUSIVAMENTE en el mapa
 * (resources/js/sede-mapa.js, Leaflet + OpenStreetMap) — no hay
 * inputs de texto para escribirla a mano.
 */
#[Layout('layouts.app')]
class SedeForm extends Component
{
    public ?Sede $sede = null;

    public string $nombre = '';

    public ?string $direccion = null;

    public ?float $latitud = null;

    public ?float $longitud = null;

    public int $radioMetros = 150;

    public bool $activo = true;

    public function mount(?Sede $sede = null): void
    {
        if ($sede?->exists) {
            $this->sede = $sede;
            $this->nombre = $sede->nombre;
            $this->direccion = $sede->direccion;
            $this->latitud = (float) $sede->latitud;
            $this->longitud = (float) $sede->longitud;
            $this->radioMetros = $sede->radio_metros;
            $this->activo = $sede->activo;
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'radioMetros' => ['required', 'integer', 'min:10'],
            'activo' => ['boolean'],
        ];
    }

    protected $messages = [
        'latitud.required' => 'Marca la ubicación en el mapa antes de guardar.',
        'longitud.required' => 'Marca la ubicación en el mapa antes de guardar.',
    ];

    public function guardar(): void
    {
        $datos = $this->validate();

        $atributos = [
            'nombre' => $datos['nombre'],
            'direccion' => $datos['direccion'],
            'latitud' => $datos['latitud'],
            'longitud' => $datos['longitud'],
            'radio_metros' => $datos['radioMetros'],
            'activo' => $datos['activo'],
        ];

        $this->sede
            ? $this->sede->update($atributos)
            : Sede::create($atributos);

        session()->flash('mensaje', $this->sede ? 'Sede actualizada.' : 'Sede creada.');

        $this->redirectRoute('sedes.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.sedes.sede-form');
    }
}
