<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use Illuminate\Database\Seeder;

/**
 * Carga el organigrama oficial de la Municipalidad Distrital de Santiago
 * (ESTRUCTURA ORGÁNICA, gestión 2023-2026, abril 2023) dentro del árbol
 * `unidades_organicas`.
 *
 * La raíz es la propia Municipalidad Distrital de Santiago (MDS). Concejo
 * Municipal, Alcaldía y Gerencia Municipal NO se cargan como unidades.
 * Los Órganos Consultivos (Consejo de Coordinación Local Distrital y Junta
 * de Delegados Vecinales y Comunales) tampoco se cargan.
 *
 * Jefaturas: no se asigna `jefe_id` aquí. Se hace después, desde
 * /unidades-organicas, cuando cada puesto tenga un usuario real. Cada
 * unidad hija de otra recibe su jefe inmediato a partir del `jefe_id` de su
 * unidad; la unidad que tiene hijos recibe el jefe de área.
 */
class UnidadOrganicaSeeder extends Seeder
{
    public function run(): void
    {
        $mds = UnidadOrganica::create([
            'nombre' => 'Municipalidad Distrital de Santiago',
            'tipo' => 'alta_direccion',
        ]);

        // --- Órganos de control y defensa ---
        UnidadOrganica::create(['nombre' => 'Oficina de Control Institucional', 'tipo' => 'control', 'parent_id' => $mds->id]);
        UnidadOrganica::create(['nombre' => 'Procuraduría Pública Municipal', 'tipo' => 'control', 'parent_id' => $mds->id]);

        // --- Órganos de apoyo a Alcaldía ---
        UnidadOrganica::create(['nombre' => 'Oficina de Secretaría Municipal y Gestión Documentaria', 'tipo' => 'apoyo_alcaldia', 'parent_id' => $mds->id]);

        // --- Órganos de apoyo (solitas, con jefe de área) ---
        foreach ([
            'Oficina de Tecnologías e Informática',
            'Oficina de Imagen Institucional y Comunicaciones',
            'Oficina de Ejecución Coactiva',
        ] as $nombre) {
            UnidadOrganica::create(['nombre' => $nombre, 'tipo' => 'apoyo', 'parent_id' => $mds->id]);
        }

        // --- Oficina General de Administración y Finanzas (apoyo, con oficinas hijas) ---
        $adminFinanzas = UnidadOrganica::create(['nombre' => 'Oficina General de Administración y Finanzas', 'tipo' => 'apoyo', 'parent_id' => $mds->id]);
        foreach ([
            'Oficina de Recursos Humanos',
            'Oficina de Logística, Almacén y Patrimonio',
            'Oficina de Contabilidad',
            'Oficina de Tesorería',
        ] as $nombre) {
            UnidadOrganica::create(['nombre' => $nombre, 'tipo' => 'apoyo', 'parent_id' => $adminFinanzas->id]);
        }

        // --- Oficina General de Administración Tributaria (apoyo, con oficinas hijas) ---
        $adminTributaria = UnidadOrganica::create(['nombre' => 'Oficina General de Administración Tributaria', 'tipo' => 'apoyo', 'parent_id' => $mds->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Recaudación Tributaria', 'tipo' => 'apoyo', 'parent_id' => $adminTributaria->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Fiscalización Tributaria', 'tipo' => 'apoyo', 'parent_id' => $adminTributaria->id]);

        // --- Órganos de asesoramiento ---
        UnidadOrganica::create(['nombre' => 'Oficina de Gestión de Riesgos de Desastres', 'tipo' => 'asesoramiento', 'parent_id' => $mds->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Estudios y Proyectos Definitivos', 'tipo' => 'asesoramiento', 'parent_id' => $mds->id]);

        $supervisionInversiones = UnidadOrganica::create(['nombre' => 'Oficina General de Supervisión y Liquidación de Inversiones', 'tipo' => 'asesoramiento', 'parent_id' => $mds->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Supervisión', 'tipo' => 'asesoramiento', 'parent_id' => $supervisionInversiones->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Liquidación', 'tipo' => 'asesoramiento', 'parent_id' => $supervisionInversiones->id]);

        UnidadOrganica::create(['nombre' => 'Oficina General de Asesoría Jurídica', 'tipo' => 'asesoramiento', 'parent_id' => $mds->id]);

        $planeamiento = UnidadOrganica::create(['nombre' => 'Oficina General de Planeamiento, Presupuesto e Inversión', 'tipo' => 'asesoramiento', 'parent_id' => $mds->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Planeamiento, Modernización y Cooperación Técnica', 'tipo' => 'asesoramiento', 'parent_id' => $planeamiento->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Programación Multianual de Inversiones', 'tipo' => 'asesoramiento', 'parent_id' => $planeamiento->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Formulación de Proyectos de Pre Inversión', 'tipo' => 'asesoramiento', 'parent_id' => $planeamiento->id]);
        UnidadOrganica::create(['nombre' => 'Oficina de Presupuesto', 'tipo' => 'asesoramiento', 'parent_id' => $planeamiento->id]);

        // --- Gerencias de línea (2do nivel) con sus Sub Gerencias (3er nivel) ---
        $gerenciasDeLinea = [
            'Gerencia de Infraestructura' => [
                'Sub Gerencia de Obras',
                'Sub Gerencia de Mantenimiento de Infraestructura',
                'Sub Gerencia de Equipo Mecánico',
            ],
            'Gerencia de Desarrollo Urbano y Rural' => [
                'Sub Gerencia de Administración y Control Urbano-Rural',
                'Sub Gerencia de Saneamiento Físico Legal',
                'Sub Gerencia de Ordenamiento Territorial y Catastro',
            ],
            'Gerencia de Desarrollo Social y Cultura' => [
                'Sub Gerencia de Participación, Educación, Deporte y Juventud',
                'Sub Gerencia de Salud, Programas Sociales y Bienestar de la Persona',
                'Sub Gerencia de Cultura y Conservación',
            ],
            'Gerencia de Desarrollo Económico Local' => [
                'Sub Gerencia de Desarrollo Agropecuario y Rural',
                'Sub Gerencia de Competitividad Económica y Turismo',
                'Sub Gerencia de Comercio, Policía Municipal y Control',
            ],
            'Gerencia de Servicios Municipales' => [
                'Sub Gerencia de Seguridad Ciudadana y Monitoreo',
                'Sub Gerencia de Administración de Locales',
                'Sub Gerencia de Tránsito',
                'Sub Gerencia de Registro Civil',
            ],
            'Gerencia de Gestión del Medio Ambiente' => [
                'Sub Gerencia de Gestión de Residuos Sólidos',
                'Sub Gerencia de Gestión, Fiscalización y Saneamiento Ambiental',
                'Sub Gerencia de Gestión de Recursos y Zoonosis',
            ],
        ];

        foreach ($gerenciasDeLinea as $nombreGerencia => $subGerencias) {
            $gerencia = UnidadOrganica::create([
                'nombre' => $nombreGerencia,
                'tipo' => 'linea_2do_nivel',
                'parent_id' => $mds->id,
            ]);

            foreach ($subGerencias as $nombreSub) {
                UnidadOrganica::create([
                    'nombre' => $nombreSub,
                    'tipo' => 'linea_3er_nivel',
                    'parent_id' => $gerencia->id,
                ]);
            }
        }
    }
}
