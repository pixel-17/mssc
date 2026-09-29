<?php

namespace App\Livewire\Turnos;

use App\Livewire\Concerns\RequiereAdmin;
use App\Livewire\Configuraciones\ConfiguracionForm;
use App\Models\Configuracion;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Catálogo de Turnos del admin: SOLO define a qué hora empieza y
 * termina cada turno (Mañana, Tarde, Noche y Día). No programa a
 * ningún trabajador — eso lo hacen los jefes en el calendario de
 * equipo, eligiendo entre estos turnos.
 *
 * Las horas viven donde ya las lee todo el sistema: las claves
 * TURNO_<CODIGO>_HORA_INICIO / _FIN de `configuraciones` (ver
 * GeneradorTurnoMensualService::horasDe). Esta pantalla es una vista
 * ordenada de esas ocho claves; Configuraciones sigue mostrándolas.
 */
#[Layout('layouts.app')]
#[Title('Turnos')]
class DefinicionTurnos extends Component
{
    use RequiereAdmin;

    /** @var array<string, array{nombre: string, sigla: string, regimen: string}> */
    public const TURNOS = [
        'MANANA' => ['nombre' => 'Mañana', 'sigla' => 'M', 'regimen' => '728'],
        'TARDE' => ['nombre' => 'Tarde', 'sigla' => 'T', 'regimen' => '728'],
        'NOCHE' => ['nombre' => 'Noche', 'sigla' => 'N', 'regimen' => '728'],
        'DIA' => ['nombre' => 'Día', 'sigla' => 'DIA', 'regimen' => '276'],
    ];

    /** @var array<string, array{inicio: string, fin: string}> código => horas en H:i */
    public array $horas = [];

    public function mount(GeneradorTurnoMensualService $generador): void
    {
        $this->autorizarAdmin();

        foreach (array_keys(self::TURNOS) as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);

            $this->horas[$codigo] = [
                'inicio' => substr($inicio, 0, 5),
                'fin' => substr($fin, 0, 5),
            ];
        }
    }

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        $reglas = [];

        foreach (array_keys(self::TURNOS) as $codigo) {
            // Mismo formato H:i que exige Configuraciones para estas claves.
            $reglas["horas.{$codigo}.inicio"] = ConfiguracionForm::reglasParaClave("TURNO_{$codigo}_HORA_INICIO");
            $reglas["horas.{$codigo}.fin"] = [
                ...ConfiguracionForm::reglasParaClave("TURNO_{$codigo}_HORA_FIN"),
                "different:horas.{$codigo}.inicio",
            ];
        }

        return $reglas;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'horas.*.inicio.required' => 'Indica la hora de inicio.',
            'horas.*.fin.required' => 'Indica la hora de fin.',
            'horas.*.inicio.date_format' => 'Usa el formato HH:MM de 24 horas, por ejemplo 06:00.',
            'horas.*.fin.date_format' => 'Usa el formato HH:MM de 24 horas, por ejemplo 14:00.',
            'horas.*.fin.different' => 'La hora de fin no puede ser igual a la de inicio.',
        ];
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        DB::transaction(function () use ($datos) {
            foreach (array_keys(self::TURNOS) as $codigo) {
                foreach (['inicio' => 'INICIO', 'fin' => 'FIN'] as $campo => $sufijo) {
                    // saved() en Configuracion invalida la caché de valorDe().
                    Configuracion::updateOrCreate(
                        ['clave' => "TURNO_{$codigo}_HORA_{$sufijo}"],
                        ['valor' => $datos['horas'][$codigo][$campo]],
                    );
                }
            }
        });

        session()->now('mensaje', 'Horas de los turnos guardadas.');
    }

    public function render(): View
    {
        return view('livewire.turnos.definicion-turnos', [
            'turnos' => self::TURNOS,
        ]);
    }
}
