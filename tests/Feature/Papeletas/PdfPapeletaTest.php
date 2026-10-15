<?php

namespace Tests\Feature\Papeletas;

use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\Vencida;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Descarga del PDF de una papeleta individual (trabajador.papeletas.pdf).
 * La autorización vive en PapeletaPolicy::view; además, el PDF certifica
 * una papeleta ya aprobada, así que PdfController::papeleta solo lo permite
 * en Cerrada o Finalizada. Rechazada, Cancelada y Vencida son estados
 * terminales pero no permiten la descarga: no hay nada que certificar.
 */
class PdfPapeletaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        $this->trabajador = $this->usuarioDePrueba();
    }

    public static function estadosQuePermitenDescarga(): array
    {
        return [
            'cerrada' => [Cerrada::class],
            'finalizada' => [Finalizada::class],
        ];
    }

    public static function estadosQueNoPermitenDescarga(): array
    {
        return [
            'pendiente de jefe' => [PendienteJefe::class],
            'observada por jefe' => [ObservadaPorJefe::class],
            'pendiente de RRHH' => [PendienteRrhh::class],
            'autorizada y corriendo' => [AutorizadaYCorriendo::class],
            'rechazada' => [Rechazada::class],
            'cancelada' => [Cancelada::class],
            'vencida' => [Vencida::class],
        ];
    }

    #[DataProvider('estadosQuePermitenDescarga')]
    public function test_el_dueno_descarga_el_pdf_cuando_esta_cerrada_o_finalizada(string $estado): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, $estado);

        $respuesta = $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.pdf', $papeleta));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');
        $respuesta->assertHeader(
            'content-disposition',
            'attachment; filename=papeleta-'.str_pad((string) $papeleta->id, 4, '0', STR_PAD_LEFT).'.pdf'
        );
    }

    #[DataProvider('estadosQueNoPermitenDescarga')]
    public function test_no_se_puede_descargar_fuera_de_cerrada_o_finalizada(string $estado): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, $estado);

        $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.pdf', $papeleta))
            ->assertForbidden();
    }

    public function test_otro_trabajador_no_puede_descargar_la_papeleta_ajena(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, Cerrada::class);
        $otro = $this->usuarioDePrueba();

        $this->actingAs($otro)
            ->get(route('trabajador.papeletas.pdf', $papeleta))
            ->assertForbidden();
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, Cerrada::class);

        $this->get(route('trabajador.papeletas.pdf', $papeleta))
            ->assertRedirect(route('login'));
    }

    public function test_el_admin_sin_rol_trabajador_no_accede_a_la_ruta_de_trabajador(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, Cerrada::class);
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->actingAs($admin)
            ->get(route('trabajador.papeletas.pdf', $papeleta))
            ->assertForbidden();
    }

    public function test_una_papeleta_inexistente_responde_404(): void
    {
        $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.pdf', 999999))
            ->assertNotFound();
    }
}
