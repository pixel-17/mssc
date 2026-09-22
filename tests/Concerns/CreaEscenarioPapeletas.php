<?php

namespace Tests\Concerns;

use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use Database\Seeders\MotivoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Armado mínimo de datos para probar el flujo de papeletas sin depender
 * de horarios ni de la hora real: las papeletas se crean directamente en
 * el estado que interesa (no pasan por CrearPapeletaAction).
 */
trait CreaEscenarioPapeletas
{
    private ?Sede $sedeCompartida = null;

    protected function prepararBase(): void
    {
        $this->seed([RoleSeeder::class, MotivoSeeder::class]);

        // Las Actions notifican al terminar; aquí solo importa el efecto en BD.
        Notification::fake();
    }

    protected function sedeDePrueba(): Sede
    {
        return $this->sedeCompartida ??= Sede::create([
            'nombre' => 'Sede central',
            'latitud' => -13.5170000,
            'longitud' => -71.9780000,
        ]);
    }

    protected function motivoDe(string $codigo = 'PARTICULAR'): Motivo
    {
        return Motivo::where('codigo', $codigo)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<int, string>  $roles
     */
    protected function usuarioDePrueba(array $atributos = [], array $roles = ['trabajador']): User
    {
        $usuario = User::factory()->create([
            'regimen' => '728',
            'sede_id' => $this->sedeDePrueba()->id,
            ...$atributos,
        ]);

        foreach ($roles as $rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    /**
     * @param  class-string  $estado
     * @param  array<string, mixed>  $atributos
     */
    protected function papeletaDePrueba(User $trabajador, string $estado = PendienteJefe::class, array $atributos = []): Papeleta
    {
        return Papeleta::create([
            'trabajador_id' => $trabajador->id,
            'motivo_id' => $this->motivoDe()->id,
            'sede_id' => $trabajador->sede_id ?? $this->sedeDePrueba()->id,
            'regimen' => $trabajador->regimen ?? '728',
            'dia_operativo' => now()->toDateString(),
            'estado' => $estado,
            'jefe_inmediato_id' => $trabajador->jefe_inmediato_id,
            'jefe_area_id' => $trabajador->jefe_area_id,
            ...$atributos,
        ]);
    }

    /**
     * Turno vigente "de sobra" para el 728 de prueba: cubre el día
     * completo de `now()` (00:00-23:59:59) para que CrearPapeletaAction
     * no lo bloquee por falta de turno, sin depender de a qué hora
     * exacta corre el test. Para probar el cruce de medianoche o el
     * bloqueo por descanso, pasa horas/es_descanso explícitos.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function turnoDePrueba(User $trabajador, array $atributos = []): Turno
    {
        return Turno::create([
            'user_id' => $trabajador->id,
            'sede_id' => $trabajador->sede_id ?? $this->sedeDePrueba()->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '00:00:00',
            'hora_fin' => '23:59:59',
            'es_descanso' => false,
            'turno' => null,
            ...$atributos,
        ]);
    }
}
