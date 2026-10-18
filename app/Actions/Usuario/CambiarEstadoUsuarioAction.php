<?php

namespace App\Actions\Usuario;

use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Organigrama\PapeletasAbiertasService;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Activar / desactivar a un usuario. Única puerta: la usan la lista de
 * Usuarios, el formulario de edición y el panel de trabajador del
 * organigrama, para que las tres apliquen exactamente las mismas reglas.
 *
 * Al desactivar también se retira su turno (configuración y días de hoy en
 * adelante, ver GeneradorTurnoMensualService::retirarTurno): un usuario
 * inactivo no debe conservar turno vigente. El historial pasado se mantiene.
 *
 * NUNCA se borra un usuario: papeletas.trabajador_id tiene cascadeOnDelete
 * y borrarlo se llevaría su historial. Se desactiva; EnsureUsuarioActivo y
 * el login ya lo dejan afuera.
 *
 * Reglas para desactivar (motivoParaNoDesactivar):
 * - No a uno mismo.
 * - No al único RR. HH. activo (sin él toda papeleta se autorizaría sola).
 *
 * Si el usuario es jefe (inmediato o de área) de una unidad que todavía
 * tiene personas o sub-unidades, hay que decidir qué pasa con esa jefatura
 * (jefaturasPorResolver / desactivar($reemplazos)):
 * - 'existente': otra persona ya registrada pasa a ser el jefe.
 * - 'nuevo': se crea un usuario nuevo y queda como jefe.
 * - 'vacante': la unidad queda sin jefe. Solo quedan sin poder crear
 *   papeletas quienes dependen directamente de esa jefatura (ver
 *   CrearPapeletaAction); el resto del organigrama no se ve afectado.
 * Las papeletas abiertas que esperaban al jefe pasan al jefe nuevo (ver
 * PapeletasAbiertasService).
 */
class CambiarEstadoUsuarioAction
{
    public const REEMPLAZO_EXISTENTE = 'existente';

    public const REEMPLAZO_NUEVO = 'nuevo';

    public const REEMPLAZO_VACANTE = 'vacante';

    /** null = se puede desactivar; texto = el motivo por el que no. */
    public function motivoParaNoDesactivar(User $actor, User $usuario): ?string
    {
        if ($usuario->is($actor)) {
            return 'No puedes desactivar tu propia cuenta.';
        }

        if ($usuario->esUnicoRrhhActivo()) {
            return 'No puedes desactivar al único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa o reactiva primero a otra persona con rol RR. HH.';
        }

        return null;
    }

    /**
     * Unidades que encabeza $usuario y que todavía tienen personas activas o
     * sub-unidades: para cada una hay que decidir qué pasa con la jefatura.
     *
     * @return Collection<int, array{unidad: UnidadOrganica, es_area: bool, personas: int, subunidades: int, pendientes: int}>
     */
    public function jefaturasPorResolver(User $usuario): Collection
    {
        $pendientes = app(PapeletasAbiertasService::class)->contarPendientesDeJefe($usuario);

        return $usuario->unidadesQueEncabeza()->get()
            ->map(fn (UnidadOrganica $unidad) => [
                'unidad' => $unidad,
                'es_area' => $unidad->hijos()->where('activo', true)->exists(),
                'personas' => $unidad->miembros()->where('activo', true)->whereKeyNot($usuario->getKey())->count(),
                'subunidades' => $unidad->hijos()->where('activo', true)->count(),
                'pendientes' => $pendientes,
            ])
            ->filter(fn (array $fila) => $fila['personas'] > 0 || $fila['subunidades'] > 0)
            ->values();
    }

    /**
     * @param  array<int|string, array{modo?: string, usuario_id?: int|string|null, nuevo?: array<string, mixed>}>  $reemplazos  unidad_id => decisión
     *
     * @throws UsuarioException
     */
    public function desactivar(User $actor, User $usuario, array $reemplazos = []): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            return $usuario;
        }

        $motivo = $this->motivoParaNoDesactivar($actor, $usuario);

        if ($motivo !== null) {
            throw new UsuarioException($motivo);
        }

        $porResolver = $this->jefaturasPorResolver($usuario);

        // Todo se revisa ANTES de tocar nada: cada jefatura con gente exige una decisión explícita.
        foreach ($porResolver as $fila) {
            $decision = $reemplazos[$fila['unidad']->id] ?? null;
            $modo = $decision['modo'] ?? null;

            if (! in_array($modo, [self::REEMPLAZO_EXISTENTE, self::REEMPLAZO_NUEVO, self::REEMPLAZO_VACANTE], true)) {
                throw new UsuarioException(
                    'Decide qué pasa con la jefatura de «'.$fila['unidad']->nombre.'»: elegir a otra persona, crear un jefe nuevo o dejarla sin jefe.'
                );
            }
        }

        DB::transaction(function () use ($actor, $usuario, $reemplazos) {
            foreach ($usuario->unidadesQueEncabeza()->get() as $unidad) {
                $this->relevarJefatura($actor, $unidad, $usuario, $reemplazos[$unidad->id] ?? ['modo' => self::REEMPLAZO_VACANTE]);
            }

            $usuario->forceFill(['activo' => false])->save();
            $usuario->tokens()->delete();
            app(GeneradorTurnoMensualService::class)->retirarTurno($usuario);
        });

        return $usuario;
    }

    /**
     * Quién encabeza $unidad después de la baja. Usa la misma acción que el
     * catálogo (GuardarUnidadOrganicaAction), así que valen sus reglas
     * (jefe activo, no administrador, no encabeza ya otra unidad).
     *
     * @param  array{modo?: string, usuario_id?: int|string|null, nuevo?: array<string, mixed>}  $decision
     *
     * @throws UsuarioException
     */
    private function relevarJefatura(User $actor, UnidadOrganica $unidad, User $saliente, array $decision): void
    {
        $modo = $decision['modo'] ?? self::REEMPLAZO_VACANTE;

        $guardar = fn (?int $jefeId, bool $ubicar) => app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $unidad->fresh(),
            [
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo,
                'parent_id' => $unidad->parent_id,
                'jefe_id' => $jefeId,
                'activo' => $unidad->activo,
            ],
            ubicarJefe: $ubicar,
        );

        if ($modo === self::REEMPLAZO_EXISTENTE) {
            $reemplazoId = (int) ($decision['usuario_id'] ?? 0);

            if ($reemplazoId === (int) $saliente->id) {
                throw new UsuarioException('El reemplazo de «'.$unidad->nombre.'» no puede ser la misma persona.');
            }

            $candidato = User::find($reemplazoId)
                ?? throw new UsuarioException('Elige a quién reemplaza en «'.$unidad->nombre.'».');

            $guardar($reemplazoId, (int) $candidato->unidad_organica_id !== (int) $unidad->id);

            return;
        }

        // Vacante (o paso previo a crear al nuevo: la unidad debe estar sin jefe).
        $guardar(null, false);

        if ($modo === self::REEMPLAZO_NUEVO) {
            $this->crearJefeNuevo($actor, $unidad, $saliente, (array) ($decision['nuevo'] ?? []));
        }
    }

    /**
     * Crea el usuario nuevo con la misma acción que el alta de jefes y lo deja
     * como jefe de la unidad (el alta lo asigna al estar la unidad sin jefe).
     *
     * @param  array<string, mixed>  $nuevo  name, apellido, dni, email, regimen, sede_id (opcional)
     *
     * @throws UsuarioException
     */
    private function crearJefeNuevo(User $actor, UnidadOrganica $unidad, User $saliente, array $nuevo): void
    {
        foreach (['name', 'apellido', 'dni', 'email', 'regimen'] as $campo) {
            if (empty($nuevo[$campo])) {
                throw new UsuarioException('Completa los datos del jefe nuevo de «'.$unidad->nombre.'» (nombre, apellido, DNI, correo y régimen).');
            }
        }

        if (! in_array($nuevo['regimen'], ['276', '728'], true)) {
            throw new UsuarioException('El régimen del jefe nuevo debe ser 276 o 728.');
        }

        app(CrearUsuarioAction::class)->ejecutar($actor, [
            'name' => $nuevo['name'],
            'apellido' => $nuevo['apellido'],
            'dni' => $nuevo['dni'],
            'email' => $nuevo['email'],
            'regimen' => $nuevo['regimen'],
            'sede_id' => $nuevo['sede_id'] ?? $saliente->sede_id,
            'unidad_organica_id' => $unidad->id,
            'tipo' => 'jefe_inmediato',
        ], true);
    }

    /** @throws UsuarioException */
    public function reactivar(User $actor, User $usuario): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            $usuario->forceFill(['activo' => true])->save();
        }

        return $usuario;
    }

    private function exigirAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede activar o desactivar usuarios.');
        }
    }
}
