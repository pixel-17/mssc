<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mueve SOLO a la persona (jefe inmediato o jefe de área) a otra unidad,
 * sin arrastrar la unidad que encabezaba. A diferencia de
 * MoverJefeInmediatoAction (que mueve la unidad entera con su gente), aquí la
 * unidad se queda donde está con sus trabajadores y la persona pasa a ser
 * TRABAJADOR de la unidad destino.
 *
 * Reglas (todas validadas en el servidor):
 * - Solo admin.
 * - Por cada unidad que encabeza, si hay alguien elegible (para un jefe de
 *   área, otro jefe de área) se OFRECE elegir un reemplazo, pero es opcional:
 *   si no se elige a nadie —haya o no candidatos— la unidad simplemente
 *   queda sin jefe (se avisa).
 * - El reemplazo pasa las mismas reglas que cambiar el jefe desde la unidad
 *   (JefaturaUnidadService::validar: activo, no encabeza ya otra unidad) y pasa a pertenecer a esa unidad.
 * - Al entrar al destino se comporta como un trabajador más, igual que uno
 *   recién creado: la sede por defecto es la del jefe de la unidad destino,
 *   su jefe inmediato y de área los recalcula el observer y, si es 728,
 *   necesita turno (los jefes no tienen turno; los trabajadores sí). Si ya tenía su propia
 *   configuración de turno, se conserva. En 276 no se pide turno.
 *
 * El traslado final lo hace MoverTrabajadorAction, así queda en
 * movimientos_organigrama con sus mismas validaciones.
 *
 * Quién releva a cada jefe (en lo que deja y en lo que gana) lo resuelve
 * ReemplazoDeJefeService: esta clase valida que el movimiento en sí sea
 * posible y orquesta los pasos (relevar jefaturas, trasladar, cargar turno).
 */
class MoverJefeComoTrabajadorAction
{
    /** La persona entra al destino como un trabajador más. */
    public const COMO_TRABAJADOR = 'trabajador';

    /**
     * La persona entra al destino como jefe de la unidad: si ya tenía jefe,
     * lo releva y ese sigue en la unidad como trabajador. Conserva su sede y
     * no recibe turno (se programa el suyo).
     */
    public const COMO_JEFE = 'jefe';

    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
        private ReemplazoDeJefeService $reemplazoDeJefe,
    ) {}

    /**
     * Qué pasaría, sin guardar nada. Lanza UsuarioException si no se puede.
     *
     * @return array{
     *     jefe: User,
     *     destino: UnidadOrganica,
     *     unidades: Collection<int, array{unidad: UnidadOrganica, es_area: bool, personas: int, subunidades: int, requiere_reemplazo: bool, candidatos: Collection<int, User>}>,
     *     sede: array{actual: ?Sede, propuesta: ?Sede},
     *     necesita_turno: bool,
     *     turnos_validos: list<string>,
     *     avisos: list<string>
     * }
     */
    public function previsualizar(User $actor, User $jefe, UnidadOrganica $destino, string $modo = self::COMO_TRABAJADOR): array
    {
        $unidades = $this->validar($actor, $jefe, $destino, $modo);
        $destino->loadMissing('jefe.sede');

        $avisos = [];
        $filas = $unidades->map(function (UnidadOrganica $unidad) use ($jefe, &$avisos) {
            $subunidades = $unidad->hijos()->count();
            $personas = $this->reemplazoDeJefe->miembrosActivos($unidad, $jefe)->count();

            // Hay candidatos a quien OFRECER como reemplazo (dejarlo es siempre
            // opcional, nunca obligatorio: si no se elige a nadie, la unidad
            // queda sin jefe). Hay que pasar por el servicio y no solo contar
            // gente/sub-unidades: esa gente además tiene que poder ser jefe
            // (JefaturaUnidadService::validar()).
            $candidatos = $this->reemplazoDeJefe->candidatos($unidad, $jefe);

            if ($candidatos->isEmpty()) {
                $avisos[] = '«'.$unidad->nombre.'» '.($personas > 0 || $subunidades > 0
                    ? 'no tiene a quién dejarle el puesto'
                    : 'no tiene más personas').': quedará sin jefe.';
            }

            return [
                'unidad' => $unidad,
                'es_area' => $subunidades > 0,
                'personas' => $personas,
                'subunidades' => $subunidades,
                'requiere_reemplazo' => $candidatos->isNotEmpty(),
                'candidatos' => $candidatos,
            ];
        })->values();

        $comoJefe = $modo === self::COMO_JEFE;
        $jefesDestino = $this->reemplazoDeJefe->jefesDelDestino($destino, $jefe);
        $reemplazaDestino = $comoJefe && $this->reemplazoDeJefe->requiereReemplazarDestino($destino, $jefe);
        $necesitaTurno = ! $comoJefe && $this->necesitaTurno($jefe);

        if ($reemplazaDestino) {
            $avisos[] = '«'.$destino->nombre.'» ya tiene jefe: '.$jefe->nombre_completo.' lo releva y el actual seguirá en la unidad como trabajador.';
        }

        if ($necesitaTurno) {
            $avisos[] = $jefe->nombre_completo.' es 728: como trabajador necesita un turno y su fecha de inicio de ciclo.';
        }

        $sedePropuesta = $destino->jefe?->sede;

        return [
            'modo' => $modo,
            'jefe' => $jefe,
            'destino' => $destino,
            'unidades' => $filas,
            'jefes_destino' => $jefesDestino,
            'requiere_reemplazar_destino' => $reemplazaDestino,
            'sede' => [
                'actual' => $jefe->sede,
                'propuesta' => ! $comoJefe && $sedePropuesta && (int) $sedePropuesta->id !== (int) $jefe->sede_id ? $sedePropuesta : null,
            ],
            'necesita_turno' => $necesitaTurno,
            'turnos_validos' => ConfiguracionTurno::turnosValidosPara($jefe),
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array<int|string, int|string|null>  $reemplazos  unidad_id => user_id del reemplazo
     * @param  array{turno?: ?string, fecha_ancla?: ?string, dias_trabajo?: ?int, dias_descanso?: ?int}|null  $turno  solo para 728 sin turno propio
     *
     * @throws UsuarioException
     */
    public function ejecutar(
        User $actor,
        int $jefeId,
        int $destinoId,
        array $reemplazos,
        ?array $turno,
        bool $confirmado,
        string $modo = self::COMO_TRABAJADOR,
        ?int $reemplazaDestinoId = null,
    ): MovimientoOrganigrama {
        if (! $confirmado) {
            throw new UsuarioException('Falta confirmar explícitamente el movimiento.');
        }

        $comoJefe = $modo === self::COMO_JEFE;

        return DB::transaction(function () use ($actor, $jefeId, $destinoId, $reemplazos, $turno, $modo, $comoJefe, $reemplazaDestinoId) {
            $jefe = User::query()->lockForUpdate()->find($jefeId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::query()->lockForUpdate()->find($destinoId)
                ?? throw new UsuarioException('La unidad de destino ya no existe.');

            $unidades = $this->validar($actor, $jefe, $destino, $modo);

            // Todo lo que pueda fallar se revisa ANTES de tocar nada: reemplazos y turno.
            $plan = $unidades->mapWithKeys(fn (UnidadOrganica $unidad) => [
                $unidad->id => $this->reemplazoDeJefe->reemplazoPara($unidad, $jefe, $reemplazos),
            ]);

            $datosTurno = $comoJefe ? null : $this->validarTurno($jefe, $turno);
            $saliente = $comoJefe ? $this->reemplazoDeJefe->validarReemplazoDestino($destino, $jefe, $reemplazaDestinoId) : null;

            foreach ($unidades as $unidad) {
                $this->relevarJefatura($actor, $unidad, $jefe, $plan[$unidad->id]);
            }

            if ($comoJefe) {
                $jefe->refresh();

                return $this->colocarComoJefe($actor, $jefe, $destino, $saliente);
            }

            $jefe->refresh();

            // Ahora es un trabajador común: el traslado, la sede del jefe destino y el
            // registro en movimientos_organigrama los resuelve MoverTrabajadorAction.
            $movimiento = app(MoverTrabajadorAction::class)->ejecutar(
                $actor,
                (int) $jefe->id,
                (int) $destino->id,
                (int) $jefe->unidad_organica_id,
                true,
                cambiarSede: true,
            );

            if ($datosTurno !== null) {
                $this->generadorTurno->cargarConfiguracion(
                    trabajador: $jefe->fresh(),
                    turno: $datosTurno['turno'],
                    fechaAncla: Carbon::parse($datosTurno['fecha_ancla']),
                    actor: $actor,
                    diasTrabajo: $datosTurno['dias_trabajo'],
                    diasDescanso: $datosTurno['dias_descanso'],
                );
            }

            return $movimiento;
        });
    }

    /**
     * Única puerta de reglas de quién y adónde. Devuelve las unidades que
     * encabeza (jefe_id) y que hay que relevar.
     *
     * @return Collection<int, UnidadOrganica>
     *
     * @throws UsuarioException
     */
    private function validar(User $actor, User $jefe, UnidadOrganica $destino, string $modo = self::COMO_TRABAJADOR): Collection
    {
        if (! in_array($modo, [self::COMO_TRABAJADOR, self::COMO_JEFE], true)) {
            throw new UsuarioException('Forma de mover no válida.');
        }

        $esAdmin = $actor->hasRole('admin');
        $reglas = app(ReglasOrganigrama::class);

        if (! $esAdmin && ! $actor->esJefeDeArea()) {
            throw new UsuarioException('Solo un administrador o un jefe de área puede mover jefes.');
        }

        if (! $esAdmin) {
            if ($jefe->hasRole('admin')) {
                throw new UsuarioException('No puedes mover a un administrador.');
            }

            if ((int) $jefe->id === (int) $actor->id) {
                throw new UsuarioException('No puedes moverte a ti mismo.');
            }
        }

        $unidades = $jefe->unidadesQueEncabeza()->get();

        // Un trabajador que no encabeza nada solo puede entrar como jefe (ascenso); como trabajador se mueve con MoverTrabajadorAction.
        if ($modo === self::COMO_TRABAJADOR && $unidades->isEmpty()) {
            throw new UsuarioException($jefe->nombre_completo.' no encabeza ninguna unidad: se mueve como trabajador.');
        }

        if ($unidades->contains('id', $destino->id)) {
            throw new UsuarioException('No puedes mover a '.$jefe->nombre_completo.' a una unidad que él mismo encabeza: elige otra.');
        }

        if ($modo === self::COMO_JEFE && $destino->esJefeDeLaUnidad($jefe)) {
            throw new UsuarioException($jefe->nombre_completo.' ya es jefe inmediato de «'.$destino->nombre.'».');
        }

        // Como jefe sí puede ser su propia unidad (un trabajador asciende a jefe de la unidad donde ya está).
        if ($modo === self::COMO_TRABAJADOR && (int) $jefe->unidad_organica_id === (int) $destino->id) {
            throw new UsuarioException($jefe->nombre_completo.' ya está en «'.$destino->nombre.'».');
        }

        // Un jefe inmediato nunca queda sin sede (es esencial para los cierres).
        if ($modo === self::COMO_JEFE && $jefe->sede_id === null && $destino->jefe?->sede_id === null) {
            throw new UsuarioException($jefe->nombre_completo.' no tiene sede: asígnale una antes de hacerlo jefe inmediato (la necesita para los cierres).');
        }

        if (! $destino->activo) {
            throw new UsuarioException('La unidad de destino está desactivada.');
        }

        if ($modo === self::COMO_TRABAJADOR) {
            app(MoverTrabajadorAction::class)->exigirJefeInmediato($destino);
        }

        // Jefe de área: todo ocurre dentro de su propia área (origen, unidades que deja y destino).
        if (! $esAdmin) {
            $areaDestino = $reglas->areaDe($actor, $destino);

            if ($areaDestino === null) {
                throw new UsuarioException('La unidad de destino está fuera de tu área.');
            }

            $origen = $jefe->unidad_organica_id ? UnidadOrganica::find($jefe->unidad_organica_id) : null;

            if ($origen === null || $reglas->areaDe($actor, $origen) !== $areaDestino) {
                throw new UsuarioException($jefe->nombre_completo.' está fuera de tu área: solo un administrador puede moverlo.');
            }

            foreach ($unidades as $unidad) {
                if (! $reglas->puedeGestionarJefaturasEn($actor, $unidad) || $reglas->areaDe($actor, $unidad) !== $areaDestino) {
                    throw new UsuarioException('«'.$unidad->nombre.'» no depende de ti como jefe de área: solo un administrador puede relevar esa jefatura.');
                }
            }

            if ($modo === self::COMO_JEFE && ! $reglas->puedeGestionarJefaturasEn($actor, $destino)) {
                throw new UsuarioException('No puedes cambiar la jefatura de tu propia unidad: lo hace un administrador.');
            }
        }

        return $unidades;
    }

    /**
     * Pasa a $jefe a $destino como jefe inmediato (ver COMO_JEFE) y deja el
     * movimiento registrado. Conserva su sede; no se le carga turno.
     */
    private function colocarComoJefe(User $actor, User $jefe, UnidadOrganica $destino, ?User $saliente): MovimientoOrganigrama
    {
        $destino = UnidadOrganica::findOrFail($destino->id);

        $antes = [
            'unidad' => $jefe->unidad_organica_id,
            'sede' => $jefe->sede_id,
            'jefe_inmediato' => $jefe->jefe_inmediato_id,
            'jefe_area' => $jefe->jefe_area_id,
        ];

        // Misma acción que el catálogo: con ubicarJefe lo pasa a la unidad.
        app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $destino,
            [
                'nombre' => $destino->nombre,
                'tipo' => $destino->tipo,
                'parent_id' => $destino->parent_id,
                'jefe_id' => $jefe->id,
                'activo' => $destino->activo,
            ],
            ubicarJefe: true,
        );

        // Pertenece a la unidad destino: su superior pasa a ser el de la unidad padre (UserObserver lo recalcula).
        // Conserva su sede; solo si no tenía ninguna toma la del jefe de la unidad (nunca queda sin sede).
        $jefe->refresh();
        if ((int) $jefe->unidad_organica_id !== (int) $destino->id || $jefe->sede_id === null) {
            $jefe->unidad_organica_id = $destino->id;
            $jefe->sede_id ??= $destino->jefe?->sede_id;
            $jefe->save();
        }

        $jefe->refresh();

        return MovimientoOrganigrama::create([
            'trabajador_id' => $jefe->id,
            'actor_id' => $actor->id,
            'unidad_anterior_id' => $antes['unidad'],
            'unidad_nueva_id' => $jefe->unidad_organica_id,
            'sede_anterior_id' => $antes['sede'],
            'sede_nueva_id' => $jefe->sede_id,
            'jefe_inmediato_anterior_id' => $antes['jefe_inmediato'],
            'jefe_inmediato_nuevo_id' => $jefe->jefe_inmediato_id,
            'jefe_area_anterior_id' => $antes['jefe_area'],
            'jefe_area_nuevo_id' => $jefe->jefe_area_id,
            'revierte_id' => null,
        ]);
    }

    /**
     * @param  array{turno?: ?string, fecha_ancla?: ?string, dias_trabajo?: ?int, dias_descanso?: ?int}|null  $turno
     * @return array{turno: string, fecha_ancla: string, dias_trabajo: int, dias_descanso: int}|null null = no hay que cargar turno
     *
     * @throws UsuarioException
     */
    private function validarTurno(User $jefe, ?array $turno): ?array
    {
        if (! $this->necesitaTurno($jefe)) {
            return null;
        }

        $codigo = $turno['turno'] ?? null;
        $fecha = $turno['fecha_ancla'] ?? null;

        if (! in_array($codigo, ConfiguracionTurno::turnosValidosPara($jefe), true)) {
            throw new UsuarioException('Elige el turno de '.$jefe->nombre_completo.' en la unidad nueva (régimen 728).');
        }

        try {
            Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            throw new UsuarioException('Indica desde qué fecha empieza el ciclo de turno de '.$jefe->nombre_completo.'.');
        }

        return [
            'turno' => $codigo,
            'fecha_ancla' => Carbon::parse((string) $fecha)->toDateString(),
            'dias_trabajo' => max(1, (int) ($turno['dias_trabajo'] ?? 6)),
            'dias_descanso' => max(0, (int) ($turno['dias_descanso'] ?? 1)),
        ];
    }

    /** 728 sin configuración de turno propia: como trabajador la necesita (un 276 no lleva turno). */
    private function necesitaTurno(User $jefe): bool
    {
        return $jefe->regimen === '728'
            && ! ConfiguracionTurno::where('user_id', $jefe->id)->exists();
    }

    /** Cambia quién encabeza $unidad (reemplazo, o nadie) usando la misma acción que el catálogo. */
    private function relevarJefatura(User $actor, UnidadOrganica $unidad, User $jefe, ?int $reemplazoId): void
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return;
        }

        app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $unidad,
            [
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo,
                'parent_id' => $unidad->parent_id,
                'jefe_id' => $reemplazoId,
                'activo' => $unidad->activo,
            ],
            ubicarJefe: $reemplazoId !== null,
        );
    }
}