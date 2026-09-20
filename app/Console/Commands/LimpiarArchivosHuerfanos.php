<?php

namespace App\Console\Commands;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\Models\Sustento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos de papeletas que quedaron en el disco privado sin que ninguna
 * fila de la base de datos los referencie: un fallo entre "guardar el
 * archivo" y "guardar la fila", un proceso muerto a mitad de camino, o el
 * archivo que un sustento reemplazó al volver a presentarse.
 *
 * Los controladores ya limpian lo que pueden en el momento; esto es la red
 * de seguridad. Por prudencia:
 *  - sin --borrar SOLO lista (es el modo por defecto);
 *  - ignora archivos con menos de --dias días (por defecto 30), para no
 *    tocar una subida en curso y dejar margen de recuperación;
 *  - solo mira las carpetas conocidas de papeletas/.
 *
 * No está programado en el scheduler a propósito: bórralos a mano la primera
 * vez (revisando la lista) y, si te sirve, prográmalo después.
 */
class LimpiarArchivosHuerfanos extends Command
{
    protected $signature = 'archivos:huerfanos
        {--borrar : Elimina los archivos huérfanos (sin esto solo los lista)}
        {--dias=30 : Solo considera archivos con más de N días}';

    protected $description = 'Lista (o elimina) los archivos de papeletas que ninguna fila de la base de datos referencia.';

    /** Carpetas del disco `local` donde la aplicación guarda archivos de papeletas. */
    private const CARPETAS = [
        'papeletas/adjuntos-iniciales',
        'papeletas/retornos',
        'papeletas/sustentos',
    ];

    public function handle(): int
    {
        $dias = max(0, (int) $this->option('dias'));
        $borrar = (bool) $this->option('borrar');
        $limite = now()->subDays($dias)->getTimestamp();
        $disco = Storage::disk('local');

        $referenciados = array_flip(array_merge(
            Papeleta::whereNotNull('adjunto_inicial_path')->pluck('adjunto_inicial_path')->all(),
            Retorno::whereNotNull('foto_path')->pluck('foto_path')->all(),
            Sustento::whereNotNull('archivo_path')->pluck('archivo_path')->all(),
        ));

        $huerfanos = 0;
        $bytes = 0;

        foreach (self::CARPETAS as $carpeta) {
            foreach ($disco->files($carpeta) as $archivo) {
                if (isset($referenciados[$archivo]) || $disco->lastModified($archivo) > $limite) {
                    continue;
                }

                $huerfanos++;
                $bytes += $disco->size($archivo);

                $this->line(($borrar ? 'borrado  ' : 'huérfano ').$archivo);

                if ($borrar) {
                    $disco->delete($archivo);
                }
            }
        }

        $resumen = sprintf('%d archivo(s), %s.', $huerfanos, $this->legible($bytes));

        $this->info($borrar ? "Eliminados: {$resumen}" : "Huérfanos con más de {$dias} días: {$resumen} Usa --borrar para eliminarlos.");

        return self::SUCCESS;
    }

    private function legible(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 1).' KB';
    }
}
