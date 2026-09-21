<?php

namespace App\Livewire\Configuraciones;

use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reemplaza a ConfiguracionResource::form() de Filament. Admin solo
 * puede EDITAR el valor de cada clave (reloj del jefe, tope de
 * observaciones, bloque de almuerzo, horas de sustento, días de
 * subsanación), nunca crear ni borrar filas — por eso este componente
 * exige un registro existente (no admite modo "crear").
 */
#[Layout('layouts.app')]
#[Title('Editar configuración')]
class ConfiguracionForm extends Component
{
    use RequiereAdmin;

    #[Locked]
    public Configuracion $configuracion;

    public string $valor = '';

    public function mount(Configuracion $configuracion): void
    {
        $this->configuracion = $configuracion;
        $this->valor = $configuracion->valor;
    }

    protected function rules(): array
    {
        return ['valor' => self::reglasParaClave($this->configuracion->clave)];
    }

    /**
     * Las horas se comparan como texto "H:i" (ver HorarioOrdinarioService), así
     * que "8:00", "25:99" o "8am" no dan un error: dejan el horario roto en
     * silencio. Cada tipo de clave se valida con su formato.
     *
     * @return array<int, string>
     */
    public static function reglasParaClave(string $clave): array
    {
        if ($clave === 'MODO_ESTRICTO_728') {
            return ['required', 'string', 'in:0,1'];
        }

        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $clave)) {
            return ['required', 'string', 'date_format:H:i'];
        }

        if ($clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            return ['required', 'string', 'regex:/^[1-7](,[1-7])*$/'];
        }

        if (preg_match('/(_MINUTOS|_HORAS_HABILES|_DIAS_HABILES|^TOPE_)/', $clave)) {
            return ['required', 'integer', 'min:1', 'max:100000'];
        }

        return ['required', 'string', 'max:255'];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'valor.date_format' => 'Usa el formato de hora HH:MM de 24 horas, por ejemplo 07:45.',
            'valor.regex' => 'Usa los números de día del 1 (lunes) al 7 (domingo) separados por coma, por ejemplo 1,2,3,4,5.',
        ];
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        $this->configuracion->update($datos);

        session()->flash('mensaje', 'Configuración actualizada.');

        $this->redirectRoute('configuraciones.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-form');
    }
}
