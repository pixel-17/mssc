<?php

namespace App\Actions\UnidadOrganica;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Autoservicio de árbol para Jefe de Área (ver conversación de
 * diseño): crea una oficina nueva DENTRO de su propio subárbol y, en
 * la misma transacción, el/los jefe(s) inmediato(s) que la encabezan.
 *
 * Admin ya no participa en esto — sigue sembrando solo la raíz del
 * árbol vía UnidadOrganicaForm (unidades sin padre y su primer Jefe
 * de Área). Todo lo que cuelga de un área existente lo crea el propio
 * Jefe de Área.
 *
 * Reutiliza CrearUsuarioAction para el alta de cada jefe: DNI,
 * reingreso, contraseña = DNI y carga de turno 728 siguen validándose
 * en un solo sitio, nunca duplicados aquí.
 *
 * Regímenes:
 * - 276: siempre exactamente 1 jefe en $datosJefes. Va a jefe_id de
 *   la oficina (lo hace CrearUsuarioAction::asignarComoJefeDeUnidad
 *   porque la oficina nace con jefe_id null).
 * - 728: 1 a 3 jefes en $datosJefes, cada uno con 'turno'
 *   (MANANA/TARDE/NOCHE). El primero de la lista siempre queda como
 *   jefe_id (fallback general); a partir del segundo, cada uno se
 *   registra además en jefes_turno para su turno específico. Si solo
 *   viene 1 jefe 728, cubre los 3 turnos por el fallback normal de
 *   resolverJefeInmediato() — no hace falta tocar jefes_turno.
 */
class CrearOficinaConJefeAction
{
    public function __construct(
        private CrearUsuarioAction $crearUsuario,
        private UserPolicy $policy,
    ) {}

    /**
     * @param  array{nombre:string,tipo:?string,parent_id:int}  $datosOficina
     * @param  list<array<string,mixed>>  $datosJefes  cada uno con la misma forma que $datos de CrearUsuarioAction, más 'turno' si regimen 728 y hay más de un jefe
     */
    public function ejecutar(User $creador, array $datosOficina, array $datosJefes): UnidadOrganica
    {
        if (empty($datosJefes)) {
            throw new UsuarioException('Toda oficina nueva necesita al menos un jefe inmediato.');
        }

        if (! $this->policy->crearOficina($creador, (int) $datosOficina['parent_id'])) {
            throw new UsuarioException('Esa unidad padre no pertenece a tu área.');
        }

        return DB::transaction(function () use ($creador, $datosOficina, $datosJefes) {
            $oficina = UnidadOrganica::create([
                'nombre' => $datosOficina['nombre'],
                'tipo' => $datosOficina['tipo'] ?? null,
                'parent_id' => $datosOficina['parent_id'],
                'jefe_id' => null, // lo completa asignarComoJefeDeUnidad() con el primer jefe de abajo
                'creado_por_id' => $creador->id,
                'activo' => true,
            ]);

            foreach ($datosJefes as $indice => $datosJefe) {
                // CrearUsuarioAction también necesita 'turno' (regimen 728,
                // para cargar el ciclo de turno del propio jefe como
                // trabajador) — no se quita del array, solo se copia acá
                // para además crear la fila en jefes_turno si corresponde.
                $turno = $datosJefe['turno'] ?? null;

                $jefe = $this->crearUsuario->ejecutar(
                    creador: $creador,
                    datos: [...$datosJefe, 'unidad_organica_id' => $oficina->id, 'tipo' => 'jefe_inmediato'],
                    esJefeDeArea: true,
                );

                // El primero ya quedó como jefe_id de la oficina (fallback
                // general). Del segundo en adelante, cobertura explícita
                // por turno — solo tiene sentido en régimen 728.
                if ($indice > 0 && $datosJefe['regimen'] === '728' && $turno) {
                    JefeTurno::updateOrCreate(
                        ['unidad_organica_id' => $oficina->id, 'turno' => $turno],
                        ['jefe_id' => $jefe->id]
                    );
                }
            }

            return $oficina->fresh();
        });
    }
}
