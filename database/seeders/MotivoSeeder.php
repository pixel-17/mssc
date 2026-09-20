<?php

namespace Database\Seeders;

use App\Models\Motivo;
use Illuminate\Database\Seeder;

/**
 * Los 3 motivos del flujo (Sección 3 del documento) con sus banderas
 * de negocio. Particular es el único con es_destino_reclasificacion,
 * que ReclasificarAParticularAction usa para resolver el destino sin
 * hardcodear el código del motivo.
 */
class MotivoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            [
                'codigo' => 'PARTICULAR',
                'nombre' => 'Particular',
                'adjunto' => 'no',
                'suma_descuento' => true,
                'permite_cierre_sin_retorno' => false,
                'requiere_sustento_en_retorno' => false,
                'es_destino_reclasificacion' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'SALUD',
                'nombre' => 'Salud',
                'adjunto' => 'flexible',
                'suma_descuento' => false,
                'permite_cierre_sin_retorno' => false,
                'requiere_sustento_en_retorno' => true,
                'es_destino_reclasificacion' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'COMISION',
                'nombre' => 'Comisión de Servicio',
                'adjunto' => 'opcional',
                'suma_descuento' => false,
                'permite_cierre_sin_retorno' => true,
                'requiere_sustento_en_retorno' => false,
                'es_destino_reclasificacion' => false,
                'activo' => true,
            ],
        ];

        foreach ($motivos as $motivo) {
            Motivo::updateOrCreate(['codigo' => $motivo['codigo']], $motivo);
        }
    }
}
