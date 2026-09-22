<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un reingreso reemplaza correo y contraseña (= DNI) de una cuenta
 * existente: solo es aceptable para un ex-trabajador común desactivado.
 */
class ReingresoUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $creador;

    private UnidadOrganica $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->creador = $this->usuarioDePrueba();
        $this->unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->creador->id]);
    }

    /** @return array<string, mixed> */
    private function datos(string $dni, string $email = 'nuevo@example.com'): array
    {
        return [
            'name' => 'Rosa',
            'apellido' => 'Quispe',
            'dni' => $dni,
            'email' => $email,
            'regimen' => '728',
            'sede_id' => $this->sedeDePrueba()->id,
            'unidad_organica_id' => $this->unidad->id,
            'tipo' => 'trabajador',
            // 728 siempre exige turno inicial (ver CrearUsuarioAction).
            'turno' => 'MANANA',
            'fecha_ancla' => now()->toDateString(),
        ];
    }

    private function crear(array $datos): User
    {
        return app(CrearUsuarioAction::class)->ejecutar($this->creador, $datos, true);
    }

    public function test_un_alta_normal_sigue_funcionando(): void
    {
        $nuevo = $this->crear($this->datos('70112233'));

        $this->assertTrue($nuevo->activo);
        $this->assertTrue($nuevo->debe_actualizar_password);
        $this->assertTrue(Hash::check('70112233', $nuevo->password));
        $this->assertTrue($nuevo->hasRole('trabajador'));
        $this->assertTrue(
            ConfiguracionTurno::where('user_id', $nuevo->id)->exists(),
            'un 728 nuevo debe quedar con su turno inicial cargado, no puede quedar sin horario'
        );
    }

    public function test_reingresa_a_un_ex_trabajador_desactivado_conservando_su_id_y_sin_su_2fa(): void
    {
        $antiguo = $this->usuarioDePrueba([
            'dni' => '70112233',
            'email' => 'viejo@example.com',
            'activo' => false,
            'two_factor_secret' => 'secreto-viejo',
            'two_factor_recovery_codes' => 'codigos-viejos',
        ]);

        $reingresado = $this->crear($this->datos('70112233', 'nuevo@example.com'));

        $this->assertSame($antiguo->id, $reingresado->id, 'misma fila: conserva su historial');
        $this->assertTrue($reingresado->activo);
        $this->assertSame('nuevo@example.com', $reingresado->email);
        $this->assertNull($reingresado->two_factor_secret);
        $this->assertNull($reingresado->two_factor_recovery_codes);
        $this->assertTrue(Hash::check('70112233', $reingresado->password));
    }

    public function test_no_reingresa_una_cuenta_desactivada_con_rol_de_admin_o_rrhh(): void
    {
        foreach (['admin', 'rrhh'] as $i => $rol) {
            $dni = '7011223'.$i;
            $exPrivilegiado = $this->usuarioDePrueba(
                ['dni' => $dni, 'email' => "ex-{$rol}@example.com", 'activo' => false],
                ['trabajador', $rol],
            );

            $this->assertThrows(
                fn () => $this->crear($this->datos($dni, 'atacante@example.com')),
                UsuarioException::class,
                'permisos especiales',
            );

            $intacta = $exPrivilegiado->fresh();
            $this->assertSame("ex-{$rol}@example.com", $intacta->email, 'el correo no se debe poder reemplazar');
            $this->assertFalse($intacta->activo);
        }
    }

    public function test_nunca_pisa_a_un_usuario_activo(): void
    {
        $activo = $this->usuarioDePrueba(['dni' => '70112233', 'email' => 'activo@example.com']);

        $this->assertThrows(
            fn () => $this->crear($this->datos('70112233', 'otro@example.com')),
            UsuarioException::class,
            'Ya existe un usuario activo con ese DNI.',
        );

        $this->assertSame('activo@example.com', $activo->fresh()->email);
    }
}
