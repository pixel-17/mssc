<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\AlertaJefaturaService;
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
 * - Por cada unidad que encabeza, si quedan trabajadores activos o
 *   sub-unidades, es OBLIGATORIO designar un reemplazo (para un jefe de área,
 *   otro jefe de área). Sin reemplazo no se mueve a nadie. Si la unidad tiene
 *   otros jefes de turno (728), uno de ellos asciende a titular y no hace
 *   falta elegir. Una unidad sin gente puede quedar sin jefe (se avisa).
 * - El reemplazo pasa las mismas reglas que cambiar el jefe desde la unidad
 *   (JefaturaUnidadService::validar: activo, régimen compatible, no encabeza
 *   ya otra unidad) y pasa a pertenecer a esa unidad.
 * - Al entrar al destino se comporta como un trabajador más, igual que uno
 *   recién creado: la sede por defecto es la del jefe de la unidad destino,
 *   su jefe inmediato y de área los recalcula el observer, el régimen debe
 *   coincidir con el de la unidad destino y, si es 728, necesita turno (los
 *   jefes no tienen turno; los trabajadores sí). Si ya tenía su propia
 *   configuración de turno, se conserva. En 276 no se pide turno.
 * - Deja de ser jefe inmediato adicional de otros trabajadores y de ser jefe
 *   de turno en cualquier unidad.
 *
 * El traslado final lo hace MoverTrabajadorAction, así queda en
 * movimientos_organigrama con sus mismas validaciones.
 */
class MoverJefeComoTrabajadorAction
{
    /** La persona entra al destino como un trabajador más. */
    public const COMO_TRABAJADOR = 'trabajador';

    /**
     * La persona entra al destino como jefe inmediato: en 728 se suma a los
     * jefes de turno (máximo 3 por unidad); en 276, o con los 3 puestos
     * ocupados, releva a uno de los jefes actuales, que sigue en la unidad
     * como trabajador. Conserva su sede y no recibe turno (se programa el suyo).
     */
    public const COMO_JEFE = 'jefe';

    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
        private AlertaJefaturaService $alertaJefatura,
    ) {}

    /**
     * Qué pasaría, sin guardar nada. Lanza UsuarioException si no se puede.
     *
     * @return array{
     *     jefe: User,
     *     destino: UnidadOrganica,
     *     unidades: Collection<int, array{unidad: UnidadOrganica, es_area: bool, personas: int, subunidades: int, requiere_reemplazo: bool, promueve: ?User, candidatos: Collection<int, User>}>,
     *     sede: array{actual: ?\App\Models\Sede, propuesta: ?\App\Models\Sede},
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
            $personas = $this->miembrosActivos($unidad, $jefe)->count();
            $promueve = $this->jefeDeTurnoQueAsciende($unidad, $jefe);
            $requiere = $this->requiereReemplazo($unidad, $jefe);

            if (! $requiere && (int) $unidad->jefe_id === (int) $jefe->id && $promueve === null) {
                $avisos[] = '«'.$unidad->nombre.'» no tiene más personas: quedará sin jefe.';
            }

            return [
                'unidad' => $unidad,
                'es_area' => $subunidades > 0,
                'personas' => $personas,
                'subunidades' => $subunidades,
                'requiere_reemplazo' => $requiere,
                'promueve' => $promueve,
                'candidatos' => $this->candidatos($unidad, $jefe),
            ];
        })->values();

        $comoJefe = $modo === self::COMO_JEFE;
        $jefesDestino = $this->jefesDelDestino($destino, $jefe);
        $reemplazaDestino = $comoJefe && $this->requiereReemplazarDestino($destino, $jefe);
        $necesitaTurno = ! $comoJefe && $this->necesitaTurno($jefe);

        if ($reemplazaDestino) {
            $avisos[] = $jefe->regimen === '728'
                ? '«'.$destino->nombre.'» ya tiene sus 3 jefes inmediatos: elige a cuál releva; seguirá en la unidad como trabajador y, siendo 728, necesitará su turno.'
                : '«'.$destino->nombre.'» solo admite un jefe inmediato (276): releva al actual, que seguirá en la unidad como trabajador.';
        }

        if ($necesitaTurno) {
            $avisos[] = $jefe->nombre_completo.' es 728: como trabajador necesita un turno y su fecha de inicio de ciclo.';
        }

        if (! $comoJefe && DB::table('jefes_inmediatos_adicionales')->where('jefe_inmediato_id', $jefe->id)->exists()) {
            $avisos[] = $jefe->nombre_completo.' deja de ser jefe inmediato adicional de otros trabajadores.';
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

        $resultado = DB::transaction(function () use ($actor, $jefeId, $destinoId, $reemplazos, $turno, $modo, $comoJefe, $reemplazaDestinoId) {
            $jefe = User::query()->lockForUpdate()->find($jefeId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::query()->lockForUpdate()->find($destinoId)
                ?? throw new UsuarioException('La unidad de destino ya no existe.');

            $unidades = $this->validar($actor, $jefe, $destino, $modo);

            // Todo lo que pueda fallar se revisa ANTES de tocar nada: reemplazos y turno.
            $plan = $unidades->mapWithKeys(fn (UnidadOrganica $unidad) => [
                $unidad->id => $this->reemplazoPara($unidad, $jefe, $reemplazos),
            ]);

            $datosTurno = $comoJefe ? null : $this->validarTurno($jefe, $turno);
            $saliente = $comoJefe ? $this->validarReemplazoDestino($destino, $jefe, $reemplazaDestinoId) : null;

            foreach ($unidades as $unidad) {
                $this->relevarJefatura($actor, $unidad, $jefe, $plan[$unidad->id]);
            }

            // Deja de ser jefe de turno en las unidades que deja.
            JefeTurno::where('jefe_id', $jefe->id)->delete();

            if ($comoJefe) {
                $jefe->refresh();

                return [$this->colocarComoJefe($actor, $jefe, $destino, $saliente), $destino, null];
            }

            // Como trabajador ya no es jefe adicional de nadie.
            DB::table('jefes_inmediatos_adicionales')->where('jefe_inmediato_id', $jefe->id)->delete();

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

            return [$movimiento, $destino, $datosTurno['turno'] ?? null];
        });

        [$movimiento, $destino, $turnoCargado] = $resultado;

        // Fuera de la transacción (igual que CrearUsuarioAction): un fallo al avisar nunca revierte el movimiento.
        if ($turnoCargado !== null) {
            $this->alertaJefatura->avisarSiFaltaJefeDeTurno($destino, $turnoCargado);
        }

        return $movimiento;
    }

    /**
     * Única puerta de reglas de quién y adónde. Devuelve las unidades que
     * encabeza (jefe_id) y que hay que relevar; vacío si solo es jefe de
     * turno de alguna.
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

        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede mover jefes.');
        }

        $unidades = $jefe->unidadesQueEncabeza()->get();

        // Un trabajador que no encabeza nada solo puede entrar como jefe (ascenso); como trabajador se mueve con MoverTrabajadorAction.
        if ($modo === self::COMO_TRABAJADOR && $unidades->isEmpty() && ! $jefe->turnosQueEncabeza()->exists()) {
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

        $regimenDestino = $destino->regimen();
        if ($regimenDestino !== null && $jefe->regimen !== $regimenDestino) {
            throw new UsuarioException(
                'El régimen de '.$jefe->nombre_completo.' ('.($jefe->regimen ?? 'sin régimen')
                .') no coincide con el de la unidad de destino (régimen '.$regimenDestino.').'
            );
        }

        return $unidades;
    }

    /** Trabajadores activos de la unidad, sin contar a $jefe. */
    private function miembrosActivos(UnidadOrganica $unidad, User $jefe)
    {
        return $unidad->miembros()->where('activo', true)->whereKeyNot($jefe->id);
    }

    /** Otro jefe de turno activo de la unidad que puede ascender a titular (728). */
    private function jefeDeTurnoQueAsciende(UnidadOrganica $unidad, User $jefe): ?User
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return null;
        }

        return $unidad->jefesTurno()
            ->where('jefe_id', '!=', $jefe->id)
            ->with('jefe')
            ->get()
            ->map(fn (JefeTurno $jt) => $jt->jefe)
            ->first(fn (?User $u) => $u?->activo);
    }

    /** ¿Hay que elegir un reemplazo? Sí si quedan personas o sub-unidades y nadie asciende solo. */
    private function requiereReemplazo(UnidadOrganica $unidad, User $jefe): bool
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return false;
        }

        $quedaGente = $this->miembrosActivos($unidad, $jefe)->exists() || $unidad->hijos()->exists();

        return $quedaGente && $this->jefeDeTurnoQueAsciende($unidad, $jefe) === null;
    }

    /**
     * Quienes pueden relevarlo: los trabajadores activos de la unidad y los
     * jefes de sus sub-unidades (un jefe de área puede ser relevado por otro
     * jefe). Solo los que pasan las reglas de cambio de jefe.
     *
     * @return Collection<int, User>
     */
    private function candidatos(UnidadOrganica $unidad, User $jefe): Collection
    {
        if (! $this->requiereReemplazo($unidad, $jefe)) {
            return collect();
        }

        $jefesDeHijos = $unidad->hijos()->whereNotNull('jefe_id')->pluck('jefe_id');

        return User::query()
            ->where('activo', true)
            ->whereKeyNot($jefe->id)
            ->where(fn ($q) => $q
                ->where('unidad_organica_id', $unidad->id)
                ->orWhereIn('id', $jefesDeHijos))
            ->orderBy('name')->orderBy('apellido')
            ->get()
            ->filter(function (User $candidato) use ($unidad) {
                try {
                    app(JefaturaUnidadService::class)->validar(
                        $unidad,
                        (int) $candidato->id,
                        null,
                        (int) $candidato->unidad_organica_id !== (int) $unidad->id,
                    );

                    return true;
                } catch (UsuarioException) {
                    return false;
                }
            })
            ->values();
    }

    /**
     * Quién releva a $jefe en $unidad: el elegido, o el jefe de turno que
     * asciende, o null si la unidad puede quedar sin jefe.
     *
     * @param  array<int|string, int|string|null>  $reemplazos
     *
     * @throws UsuarioException
     */
    private function reemplazoPara(UnidadOrganica $unidad, User $jefe, array $reemplazos): ?int
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return null; // solo era jefe de turno aquí: se quita su fila y listo
        }

        $elegido = (int) ($reemplazos[$unidad->id] ?? 0);

        if ($elegido > 0) {
            if ($elegido === (int) $jefe->id) {
                throw new UsuarioException('El reemplazo de «'.$unidad->nombre.'» no puede ser la misma persona.');
            }

            // Mismas reglas que cambiar el jefe desde la unidad (activo, régimen, no encabeza otra).
            $candidato = User::find($elegido)
                ?? throw new UsuarioException('El reemplazo elegido para «'.$unidad->nombre.'» ya no existe.');

            app(JefaturaUnidadService::class)->validar(
                $unidad,
                $elegido,
                null,
                (int) $candidato->unidad_organica_id !== (int) $unidad->id,
            );

            return $elegido;
        }

        if ($this->requiereReemplazo($unidad, $jefe)) {
            throw new UsuarioException(
                $jefe->nombre_completo.' encabeza «'.$unidad->nombre.'» y tiene '
                .($unidad->hijos()->exists() ? 'sub-unidades a su cargo' : 'trabajadores a su cargo')
                .': designa a su reemplazo antes de moverlo.'
            );
        }

        return $this->jefeDeTurnoQueAsciende($unidad, $jefe)?->id;
    }

    /**
     * Jefes inmediatos que tiene hoy la unidad (titular + jefes de turno),
     * sin repetir y sin contar a $excepto.
     *
     * @return Collection<int, User>
     */
    private function jefesDelDestino(UnidadOrganica $destino, User $excepto): Collection
    {
        return collect([$destino->jefe_id ? User::find($destino->jefe_id) : null])
            ->merge($destino->jefesTurno()->with('jefe')->get()->map(fn (JefeTurno $jt) => $jt->jefe))
            ->filter()
            ->reject(fn (User $u) => (int) $u->id === (int) $excepto->id)
            ->unique('id')
            ->values();
    }

    /** ¿No hay lugar para sumar a $jefe como jefe de la unidad? Entonces releva a uno. */
    private function requiereReemplazarDestino(UnidadOrganica $destino, User $jefe): bool
    {
        $jefes = $this->jefesDelDestino($destino, $jefe);

        if ($jefes->isEmpty()) {
            return false;
        }

        return $jefe->regimen === '728' ? $jefes->count() >= 3 : true;
    }

    /**
     * Jefe del destino al que releva (null si hay lugar libre).
     *
     * @throws UsuarioException
     */
    private function validarReemplazoDestino(UnidadOrganica $destino, User $jefe, ?int $elegidoId): ?User
    {
        if (! $this->requiereReemplazarDestino($destino, $jefe)) {
            return null;
        }

        $jefes = $this->jefesDelDestino($destino, $jefe);

        // 276: un solo jefe, no hay nada que elegir.
        if ($jefe->regimen !== '728' && $jefes->count() === 1) {
            return $jefes->first();
        }

        return $jefes->firstWhere('id', $elegidoId)
            ?? throw new UsuarioException('«'.$destino->nombre.'» ya tiene todos sus jefes inmediatos: elige a cuál releva '.$jefe->nombre_completo.'.');
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

        $hayTitular = $destino->jefe_id !== null && (int) $destino->jefe_id !== (int) $jefe->id;
        $seraTitular = ! $hayTitular || ($saliente !== null && (int) $saliente->id === (int) $destino->jefe_id);

        if ($saliente !== null) {
            JefeTurno::where('unidad_organica_id', $destino->id)->where('jefe_id', $saliente->id)->delete();
        }

        if ($seraTitular) {
            // Misma acción que el catálogo: valida régimen y, con ubicarJefe, lo pasa a la unidad.
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
                null,
                ubicarJefe: true,
            );
        }

        // En 728 todos los jefes (titular incluido) figuran en jefes_turno, igual que al crearlos.
        if ($jefe->regimen === '728') {
            JefeTurno::firstOrCreate(['unidad_organica_id' => $destino->id, 'jefe_id' => $jefe->id]);
        }

        if (! $seraTitular && $jefe->regimen !== '728') {
            throw new UsuarioException('«'.$destino->nombre.'» ya tiene su jefe inmediato.');
        }

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
            'jefes_adicionales_quitados' => null,
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
            null,
            ubicarJefe: $reemplazoId !== null,
        );

        // En 728 el titular también figura en jefes_turno (igual que al crearlo, ver CrearUsuarioAction).
        if ($reemplazoId !== null && User::whereKey($reemplazoId)->value('regimen') === '728') {
            JefeTurno::firstOrCreate(['unidad_organica_id' => $unidad->id, 'jefe_id' => $reemplazoId]);
        }
    }
}
