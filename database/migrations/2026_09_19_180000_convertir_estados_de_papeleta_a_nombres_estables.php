<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `papeletas.estado` pasa de guardar el nombre de clase del estado
 * (App\States\Papeleta\PendienteRrhh) a un nombre estable
 * ('pendiente_rrhh', ver `$name` en cada clase de App\States\Papeleta).
 * Así renombrar o mover una clase no rompe las filas existentes.
 *
 * Idempotente: solo toca filas que aún tienen el nombre de clase.
 * Acepta la barra invertida simple y la doble (algunas filas antiguas
 * quedaron escapadas). down() restaura el nombre de clase con barra doble.
 */
return new class extends Migration
{
    private const ESTADOS = [
        'pendiente_jefe' => 'PendienteJefe',
        'observada_por_jefe' => 'ObservadaPorJefe',
        'pendiente_rrhh' => 'PendienteRrhh',
        'observada_por_rrhh' => 'ObservadaPorRrhh',
        'autorizada_y_corriendo' => 'AutorizadaYCorriendo',
        'retorno_pendiente_sustento' => 'RetornoPendienteSustento',
        'cerrada' => 'Cerrada',
        'rechazada' => 'Rechazada',
        'vencida' => 'Vencida',
        'cancelada' => 'Cancelada',
        'finalizado_sin_retorno' => 'FinalizadoSinRetorno',
        'reclasificado_a_particular' => 'ReclasificadoAParticular',
    ];

    private const NAMESPACE = 'App\\States\\Papeleta\\';

    public function up(): void
    {
        foreach (self::ESTADOS as $nombre => $clase) {
            DB::table('papeletas')
                ->whereIn('estado', [
                    self::NAMESPACE.$clase,
                    str_replace('\\', '\\\\', self::NAMESPACE).$clase,
                ])
                ->update(['estado' => $nombre]);
        }
    }

    public function down(): void
    {
        $prefijo = str_replace('\\', '\\\\', self::NAMESPACE);

        foreach (self::ESTADOS as $nombre => $clase) {
            DB::table('papeletas')
                ->where('estado', $nombre)
                ->update(['estado' => $prefijo.$clase]);
        }
    }
};
