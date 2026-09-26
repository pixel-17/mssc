<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nodo del organigrama (Gerencia, Sub Gerencia, Oficina General, Oficina,
 * oficina de apoyo/asesoramiento/control, etc). Árbol auto-referenciado
 * sin límite de niveles ni de cantidad de nodos: cualquier unidad puede
 * tener sub-oficinas creadas en cualquier momento.
 *
 * `tipo` es solo para pintar el organigrama con los colores de la leyenda
 * original — la lógica de escalamiento de papeletas NUNCA depende de él,
 * solo de la posición relativa en el árbol (ver jefeInmediatoDe() /
 * jefeAreaDe() más abajo).
 */
class UnidadOrganica extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'parent_id',
        'jefe_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'parent_id');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    /**
     * Régimen de la unidad = el de su jefe (`jefe_id`): todos sus jefes y
     * trabajadores deben ser de ese mismo régimen. null mientras la
     * unidad todavía no tiene jefe (el primero que se cree lo define).
     */
    public function regimen(): ?string
    {
        return $this->jefe?->regimen;
    }

    /**
     * Turnos rotativos de una unidad 728 que hoy NO tienen jefe inmediato
     * activo (sin fila en jefes_turno, o con el jefe desactivado). Vacío
     * para unidades 276 o sin jefe: ahí la regla de turnos no aplica.
     *
     * @return list<string>
     */
    public function turnosSinJefe(): array
    {
        if ($this->regimen() !== '728') {
            return [];
        }

        return array_values(array_filter(
            ConfiguracionTurno::TURNOS_728,
            fn (string $turno) => $this->resolverJefeInmediato($turno) === null,
        ));
    }

    /** Jefes inmediatos adicionales de esta unidad (régimen 728). Asignación manual de QUIÉN, sin turno fijo. */
    public function jefesTurno(): HasMany
    {
        return $this->hasMany(JefeTurno::class, 'unidad_organica_id');
    }

    /**
     * Jefes inmediatos adicionales de esta unidad cuyo turno
     * CONFIGURADO (`configuraciones_turno.turno` — el que el
     * Admin/Jefe le eligió al armar su calendario, no el de hoy) es
     * el turno dado. Es la versión "estructural": no depende de si
     * hoy es su día de trabajo o de descanso, por eso la usan los
     * avisos de "falta jefe" (ver AlertaJefaturaService) y las
     * columnas fijas de `users` (ver jefaturasDe()) — no queremos
     * avisar un hueco solo porque el jefe asignado está descansando
     * hoy.
     *
     * @return \Illuminate\Support\Collection<int, JefeTurno>
     */
    private function jefesTurnoConfiguradosPara(string $turno): \Illuminate\Support\Collection
    {
        $jefeIdsDelTurno = ConfiguracionTurno::where('turno', $turno)->pluck('user_id');

        return $this->jefesTurno
            ->filter(fn (JefeTurno $jefeTurno) => $jefeTurno->jefe?->activo)
            ->filter(fn (JefeTurno $jefeTurno) => $jefeIdsDelTurno->contains($jefeTurno->jefe_id));
    }

    /**
     * Jefe inmediato de esta unidad para el turno dado.
     *
     * - Turno rotativo (MANANA/TARDE/NOCHE, régimen 728): de los
     *   jefes inmediatos adicionales de esta unidad (jefes_turno, sin
     *   turno fijo asignado), cuenta al primero cuyo turno
     *   CONFIGURADO coincide con el pedido y que sigue activo. Sin
     *   ninguno devuelve null: ese turno no tiene jefe y quien pide
     *   la papeleta debe enterarse (ver CrearPapeletaAction). Ya NO
     *   cae a `jefe_id`.
     * - Cualquier otro valor ($turno null, ej. régimen 276, o un código
     *   que no es de turno rotativo): `jefe_id` de la unidad.
     */
    public function resolverJefeInmediato(?string $turno): ?int
    {
        if (! in_array($turno, ConfiguracionTurno::TURNOS_728, true)) {
            return $this->jefe_id !== null ? (int) $this->jefe_id : null;
        }

        $jefeTurno = $this->jefesTurnoConfiguradosPara($turno)->first();

        return $jefeTurno ? (int) $jefeTurno->jefe_id : null;
    }

    /**
     * Todos los candidatos a jefe inmediato de esta unidad para el
     * turno dado — no uno solo (ver resolverJefeInmediato() para el
     * caso de un solo jefe fotografiado, que se mantiene por
     * compatibilidad).
     *
     * - Turno rotativo (MANANA/TARDE/NOCHE, régimen 728): de los
     *   jefes inmediatos adicionales de esta unidad, activos, SOLO
     *   cuentan los que hoy están "de servicio" para ese turno (su
     *   propio ciclo en configuraciones_turno, vía
     *   Turno::vigenteParaUsuario, igual que un trabajador). El jefe
     *   inmediato no tiene turno fijo asignado: aplica únicamente
     *   cuando él mismo está de turno y coincide con el de sus
     *   trabajadores — si ninguno está de servicio hoy, no hay
     *   candidatos (cae al bloqueo de CrearPapeletaAction, no hay
     *   caída a "todos los asignados" aunque no estén de servicio).
     * - Cualquier otro valor ($turno null, régimen 276, o un código
     *   que no es de turno rotativo): un único candidato, jefe_id de
     *   la unidad (o vacío si no tiene).
     *
     * @return list<int>
     */
    public function resolverJefesInmediatos(?string $turno): array
    {
        if (! in_array($turno, ConfiguracionTurno::TURNOS_728, true)) {
            return $this->jefe_id !== null ? [(int) $this->jefe_id] : [];
        }

        $deServicio = $this->jefesTurno
            ->filter(fn (JefeTurno $jefeTurno) => $jefeTurno->jefe?->activo)
            ->filter(
                fn (JefeTurno $jefeTurno) => Turno::vigenteParaUsuario($jefeTurno->jefe_id)?->codigo() === $turno
            );

        return $deServicio->pluck('jefe_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Versión de jefaturasDe() que devuelve TODOS los candidatos a
     * jefe inmediato (no uno solo), junto al jefe de área tal cual.
     * Usada por CrearPapeletaAction para fotografiar en
     * papeleta_jefes_candidatos (ver Papeleta::jefesCandidatos()).
     *
     * @return array{0: list<int>, 1: ?int}
     */
    public function jefaturasMultiplesDe(User $usuario, ?string $turno = null): array
    {
        $padre = $this->padre;

        if ($this->esJefeDeLaUnidad($usuario)) {
            return [
                $padre?->resolverJefesInmediatos($turno) ?? [],
                $padre?->padre?->jefe_id !== null ? (int) $padre->padre->jefe_id : null,
            ];
        }

        return [
            $this->resolverJefesInmediatos($turno),
            $padre?->jefe_id !== null ? (int) $padre->jefe_id : null,
        ];
    }

    /**
     * ¿$usuario es jefe de ESTA unidad? Lo es quien figura como
     * `jefe_id` o como jefe de algún turno en jefes_turno.
     */
    public function esJefeDeLaUnidad(User $usuario): bool
    {
        if ($usuario->id === null) {
            return false;
        }

        return (int) $this->jefe_id === (int) $usuario->id
            || $this->jefesTurno->contains(
                fn (JefeTurno $jefeTurno) => (int) $jefeTurno->jefe_id === (int) $usuario->id
            );
    }

    /** Trabajadores que pertenecen a esta unidad. */
    public function miembros(): HasMany
    {
        return $this->hasMany(User::class, 'unidad_organica_id');
    }

    /**
     * Jefe Inmediato de cualquier trabajador de esta unidad: el jefe de
     * la unidad misma.
     */
    public function jefeInmediato(): ?User
    {
        return $this->jefe;
    }

    /**
     * Jefe de Área de cualquier trabajador de esta unidad: el jefe de la
     * unidad padre. Si esta unidad no tiene padre (ej. Gerencia Municipal
     * colgando directo de Alcaldía), no hay Jefe de Área y el escalamiento
     * de esa unidad queda sin ese nivel.
     */
    public function jefeArea(): ?User
    {
        return $this->padre?->jefe;
    }

    /**
     * [jefe_inmediato_id, jefe_area_id] de $usuario como miembro de esta
     * unidad. Es la ÚNICA fuente de verdad de esta regla: la usan
     * UserObserver (columnas vigentes en `users`), CrearPapeletaAction
     * (fotografía en la papeleta) y el comando jefaturas:recalcular,
     * para que nunca diverjan.
     *
     * Quien encabeza la unidad (como `jefe_id` o como jefe de un turno)
     * también es miembro de ella, pero NO puede ser su propio jefe: su
     * superior está un nivel arriba (jefe
     * inmediato = jefe de la unidad padre; jefe de área = jefe de la
     * unidad abuela). Si no hay unidad padre, ese nivel queda en null.
     *
     * $turno (MANANA/TARDE/NOCHE) resuelve el jefe inmediato por turno
     * vía jefes_turno (ver resolverJefeInmediato); null usa el jefe_id
     * de la unidad tal cual (lo que siguen usando UserObserver y el
     * comando jefaturas:recalcular para las columnas fijas de `users`,
     * que no dependen del turno de hoy). Solo CrearPapeletaAction pasa
     * el turno vigente al fotografiar la papeleta.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public function jefaturasDe(User $usuario, ?string $turno = null): array
    {
        $padre = $this->padre;

        if ($this->esJefeDeLaUnidad($usuario)) {
            return [
                $padre?->resolverJefeInmediato($turno),
                $padre?->padre?->jefe_id !== null ? (int) $padre->padre->jefe_id : null,
            ];
        }

        return [
            $this->resolverJefeInmediato($turno),
            $padre?->jefe_id !== null ? (int) $padre->jefe_id : null,
        ];
    }

    /**
     * IDs de todos los descendientes (hijos, nietos, etc). Se usa para
     * impedir que al editar una unidad se le asigne como padre a sí misma
     * o a cualquiera de sus propios descendientes, lo que crearía un
     * ciclo infinito en el árbol.
     *
     * Trae TODAS las unidades en una sola query (son las oficinas del
     * organigrama, no trabajadores — un volumen chico) y arma el árbol
     * en memoria. La versión anterior recorría `hijos` recursivamente y
     * hacía 1 query por cada nodo descendiente; con un organigrama de
     * varios niveles esto se sentía en cada request que llama a esto
     * (UsuarioController, CrearUsuarioRequest, CalendarioEquipoIndex).
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $porPadre = static::query()
            ->select('id', 'parent_id')
            ->get()
            ->groupBy('parent_id');

        $ids = [];
        $pendientes = [$this->id];

        while ($pendientes) {
            $idActual = array_pop($pendientes);

            foreach ($porPadre->get($idActual, []) as $hijo) {
                $ids[] = $hijo->id;
                $pendientes[] = $hijo->id;
            }
        }

        return $ids;
    }
}
