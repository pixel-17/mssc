<?php

namespace App\Livewire\Motivos;

use App\Models\Motivo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a MotivoResource::form() de Filament. El catálogo de
 * Motivos ahora vive acá, en Blade + Livewire puro (ver rutas
 * 'motivos.*' en routes/web.php, middleware role:admin).
 *
 * Las banderas de reglas de negocio (suma_descuento, bypass, etc.)
 * son la única fuente de verdad que consultan las Actions del flujo
 * — nunca hardcodear por código en base al nombre/código del motivo.
 */
#[Layout('layouts.app')]
class MotivoForm extends Component
{
    public ?Motivo $motivo = null;

    public string $codigo = '';

    public string $nombre = '';

    public string $adjunto = 'no';

    public bool $activo = true;

    public bool $sumaDescuento = false;

    public bool $permiteBypassAprobacion = false;

    public bool $permiteCierreSinRetorno = false;

    public bool $requiereSustentoEnRetorno = false;

    public bool $participaReglaExclusividad = true;

    public bool $esDestinoReclasificacion = false;

    public function mount(?Motivo $motivo = null): void
    {
        if ($motivo?->exists) {
            $this->motivo = $motivo;
            $this->codigo = $motivo->codigo;
            $this->nombre = $motivo->nombre;
            $this->adjunto = $motivo->adjunto;
            $this->activo = $motivo->activo;
            $this->sumaDescuento = $motivo->suma_descuento;
            $this->permiteBypassAprobacion = $motivo->permite_bypass_aprobacion;
            $this->permiteCierreSinRetorno = $motivo->permite_cierre_sin_retorno;
            $this->requiereSustentoEnRetorno = $motivo->requiere_sustento_en_retorno;
            $this->participaReglaExclusividad = $motivo->participa_regla_exclusividad;
            $this->esDestinoReclasificacion = $motivo->es_destino_reclasificacion;
        }
    }

    protected function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:255',
                $this->motivo
                    ? 'unique:motivos,codigo,'.$this->motivo->id
                    : 'unique:motivos,codigo',
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'adjunto' => ['required', 'in:no,opcional,flexible,obligatorio'],
            'activo' => ['boolean'],
            'sumaDescuento' => ['boolean'],
            'permiteBypassAprobacion' => ['boolean'],
            'permiteCierreSinRetorno' => ['boolean'],
            'requiereSustentoEnRetorno' => ['boolean'],
            'participaReglaExclusividad' => ['boolean'],
            'esDestinoReclasificacion' => ['boolean'],
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        $atributos = [
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'adjunto' => $datos['adjunto'],
            'activo' => $datos['activo'],
            'suma_descuento' => $datos['sumaDescuento'],
            'permite_bypass_aprobacion' => $datos['permiteBypassAprobacion'],
            'permite_cierre_sin_retorno' => $datos['permiteCierreSinRetorno'],
            'requiere_sustento_en_retorno' => $datos['requiereSustentoEnRetorno'],
            'participa_regla_exclusividad' => $datos['participaReglaExclusividad'],
            'es_destino_reclasificacion' => $datos['esDestinoReclasificacion'],
        ];

        $this->motivo
            ? $this->motivo->update($atributos)
            : Motivo::create($atributos);

        session()->flash('mensaje', $this->motivo ? 'Motivo actualizado.' : 'Motivo creado.');

        $this->redirectRoute('motivos.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.motivos.motivo-form');
    }
}
