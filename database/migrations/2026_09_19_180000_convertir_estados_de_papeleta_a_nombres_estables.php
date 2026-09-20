<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cada clase de estado ahora declara `public static ?string $name`
 * (ver App\States\Papeleta\*), y ese es el valor que se persiste en
 * `papeletas.estado`. Antes se guardaba el nombre completo de la clase
 * (App\States\Papeleta\PendienteJefe): las filas que ya existen con ese
 * formato no se pueden leer con la configuración nueva.
 *
 * Los nombres van escritos a mano (no se lee `$name` de las clases) para
 * que esta migración siga funcionando aunque una clase se mueva o se
 * renombre después. Es idempotente: solo toca filas que todavía tienen
 * el formato viejo.
 */
return new class extends Migration
{
    private const MAPA = [
        'App\\States\\Papeleta\\PendienteJefe' => 'pendiente_jefe',
        'App\\States\\Papeleta\\ObservadaPorJefe' => 'observada_por_jefe',
        'App\\States\\Papeleta\\PendienteRrhh' => 'pendiente_rrhh',
        'App\\States\\Papeleta\\ObservadaPorRrhh' => 'observada_por_rrhh',
        'App\\States\\Papeleta\\AutorizadaYCorriendo' => 'autorizada_y_corriendo',
        'App\\States\\Papeleta\\RetornoPendienteSustento' => 'retorno_pendiente_sustento',
        'App\\States\\Papeleta\\Cerrada' => 'cerrada',
        'App\\States\\Papeleta\\FinalizadoSinRetorno' => 'finalizado_sin_retorno',
        'App\\States\\Papeleta\\ReclasificadoAParticular' => 'reclasificado_a_particular',
        'App\\States\\Papeleta\\Rechazada' => 'rechazada',
        'App\\States\\Papeleta\\Cancelada' => 'cancelada',
        'App\\States\\Papeleta\\Vencida' => 'vencida',
    ];

    public function up(): void
    {
        foreach (self::MAPA as $clase => $nombre) {
            DB::table('papeletas')->where('estado', $clase)->update(['estado' => $nombre]);
        }
    }

    public function down(): void
    {
        foreach (self::MAPA as $clase => $nombre) {
            DB::table('papeletas')->where('estado', $nombre)->update(['estado' => $clase]);
        }
    }
};
