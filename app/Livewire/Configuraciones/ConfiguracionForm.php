<?php

namespace App\Livewire\Configuraciones;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulario de Configuraciones. Admin solo
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

    /**
     * Solo se usa cuando tipoCampo() === 'dias': los checkboxes de
     * HORARIO_ORDINARIO_DIAS_LABORABLES se bindean aquí (array de strings
     * "1".."7") y updatedDiasSeleccionados() los vuelca a $valor como el
     * mismo "1,2,3,4,5" de siempre, que es lo único que guardar() usa.
     *
     * @var array<int, string>
     */
    public array $diasSeleccionados = [];

    /** Etiquetas ISO (1=Lunes ... 7=Domingo) para pintar los checkboxes de días. */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Las horas de inicio/fin de cada turno (TURNO_<CODIGO>_HORA_INICIO/FIN y,
     * para el turno Día, HORARIO_ORDINARIO_HORA_INICIO/FIN) se editan SOLO
     * desde Turnos (DefinicionTurnos): ahí se validan todas
     * juntas. Configuraciones ni las lista ni deja abrirlas por URL.
     */
    public static function esClaveDeTurno(string $clave): bool
    {
        return (bool) preg_match('/^(TURNO_[A-Z]+_HORA|HORARIO_ORDINARIO_HORA)_(INICIO|FIN)$/', $clave);
    }

    public function mount(Configuracion $configuracion): void
    {
        abort_if(self::esClaveDeTurno($configuracion->clave), 404);

        $this->configuracion = $configuracion;
        $this->valor = $configuracion->valor;

        if ($this->tipoCampo() === 'dias') {
            $this->diasSeleccionados = array_filter(explode(',', $configuracion->valor), fn ($d) => $d !== '');
        }
    }

    /**
     * Tipo de control que debe pintar la vista para esta clave: determina si
     * se usa <input type="time">, <input type="number">, los checkboxes de
     * días o el texto libre de siempre. Las reglas de validación (abajo) se
     * basan en el mismo criterio, así que un campo con el control "correcto"
     * nunca puede enviar un valor que su propia regla rechace.
     */
    public function tipoCampo(): string
    {
        return self::tipoCampoDe($this->configuracion->clave);
    }

    public static function tipoCampoDe(string $clave): string
    {
        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $clave)) {
            return 'hora';
        }

        if ($clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            return 'dias';
        }

        if (preg_match('/(_MINUTOS|_HORAS_HABILES|_DIAS_HABILES|^TOPE_|^POSTHOC_ALERTA_)/', $clave)) {
            return 'entero';
        }

        return 'texto';
    }

    /**
     * Se dispara cada vez que el admin marca o desmarca un día. Normaliza a
     * números únicos y ordenados (1..7) y los vuelca a $valor —la propiedad
     * que de verdad valida y guarda— como "1,2,3,4,5".
     */
    public function updatedDiasSeleccionados(): void
    {
        $dias = array_unique(array_map('intval', $this->diasSeleccionados));
        sort($dias);

        $this->valor = implode(',', $dias);
    }

    /** @return array<string, mixed> */
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
        return match (self::tipoCampoDe($clave)) {
            'hora' => ['required', 'string', 'date_format:H:i'],
            'dias' => ['required', 'string', 'regex:/^[1-7](,[1-7])*$/'],
            'entero' => ['required', 'integer', 'min:1', 'max:100000'],
            default => ['required', 'string', 'max:255'],
        };
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
