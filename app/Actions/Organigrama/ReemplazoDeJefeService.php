<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Extraído de MoverJefeComoTrabajadorAction: quién puede relevar a un jefe
 * en cada unidad que deja, y a quién releva (si a alguien) en la unidad
 * destino. Es la misma lógica de siempre, solo separada en su propia
 * clase porque MoverJefeComoTrabajadorAction mezclaba tres
 * responsabilidades (validar el movimiento, resolver reemplazos y
 * ejecutar el traslado) en un solo archivo de más de 500 líneas.
 *
 * No valida si el movimiento en sí es posible (eso lo sigue haciendo
 * MoverJefeComoTrabajadorAction::validar()): esta clase solo resuelve,
 * para un jefe que ya se sabe que se puede mover, quién queda a cargo de
 * lo que deja.
 */
class ReemplazoDeJefeService
{
    /** Trabajadores activos de la unidad, sin contar a $jefe. */
    public function miembrosActivos(
    UnidadOrganica $unidad,
    User $jefe
): HasMany {
    return $unidad->miembros()
        ->where('activo', true)
        ->whereKeyNot($jefe->id);
}

    /**
     * ¿Hay a quién ofrecer como reemplazo? Sí solo si, de los que quedan
     * (miembros de la unidad y jefes de sus sub-unidades), hay al menos uno
     * elegible como nuevo jefe (pasa JefaturaUnidadService::validar()). No
     * basta con que quede gente: esa gente tiene que poder ser jefe.
     *
     * Pese al nombre (se conserva para no tocar todas las llamadas), dejar
     * reemplazo NUNCA es obligatorio: esto solo decide si tiene sentido
     * mostrar el selector. Elegir a nadie siempre es válido (reemplazoPara()
     * lo permite); la unidad se queda sin jefe.
     */
    public function requiereReemplazo(UnidadOrganica $unidad, User $jefe): bool
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return false;
        }

        return $this->candidatosElegibles($unidad, $jefe)->isNotEmpty();
    }

    /**
     * Quienes pueden relevarlo: los trabajadores activos de la unidad y los
     * jefes de sus sub-unidades (un jefe de área puede ser relevado por otro
     * jefe). Solo los que pasan las reglas de cambio de jefe.
     *
     * @return Collection<int, User>
     */
    public function candidatos(UnidadOrganica $unidad, User $jefe, ?bool $requiere = null): Collection
    {
        // $requiere permite a quien ya lo calculó (previsualizar) no repetir las consultas.
        if ($requiere === false) {
            return collect();
        }

        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return collect();
        }

        return $this->candidatosElegibles($unidad, $jefe);
    }

    /**
     * Cómputo real de candidatos elegibles, sin pasar por requiereReemplazo()
     * (que a su vez usa este método): lo usan tanto requiereReemplazo() como
     * candidatos() para no duplicar la consulta ni recursionar.
     *
     * @return Collection<int, User>
     */
    private function candidatosElegibles(UnidadOrganica $unidad, User $jefe): Collection
    {
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
     * Quién releva a $jefe en $unidad: el elegido, o null si no se elige a
     * nadie. Dejar reemplazo es SIEMPRE opcional (haya o no candidatos): si
     * no se elige a nadie, la unidad simplemente se queda sin jefe.
     *
     * @param  array<int|string, int|string|null>  $reemplazos
     *
     * @throws UsuarioException
     */
    public function reemplazoPara(UnidadOrganica $unidad, User $jefe, array $reemplazos): ?int
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return null;
        }

        $elegido = (int) ($reemplazos[$unidad->id] ?? 0);

        if ($elegido === 0) {
            // No se eligió a nadie: opción válida, la unidad queda sin jefe.
            return null;
        }

        if ($elegido === (int) $jefe->id) {
            throw new UsuarioException('El reemplazo de «'.$unidad->nombre.'» no puede ser la misma persona.');
        }

        // Mismas reglas que cambiar el jefe desde la unidad (activo, no encabeza otra).
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

    /**
     * Jefe actual del destino (distinto de $jefe), o vacío si no tiene.
     *
     * @return Collection<int, User>
     */
   /**
 * Jefe actual del destino, distinto de $excepto.
 *
 * @return Collection<int, User>
 */
public function jefesDelDestino(
    UnidadOrganica $destino,
    User $excepto
): Collection {
    if ($destino->jefe_id === null) {
        return collect();
    }

    $jefe = User::find($destino->jefe_id);

    if ($jefe === null) {
        return collect();
    }

    if ((int) $jefe->id === (int) $excepto->id) {
        return collect();
    }

    return collect([$jefe]);
}

    /** ¿El destino ya tiene jefe? Entonces $jefe lo releva. */
    public function requiereReemplazarDestino(UnidadOrganica $destino, User $jefe): bool
    {
        return $this->jefesDelDestino($destino, $jefe)->isNotEmpty();
    }

    /** Jefe del destino al que releva (null si no tenía). */
    public function validarReemplazoDestino(UnidadOrganica $destino, User $jefe, ?int $elegidoId): ?User
    {
        return $this->jefesDelDestino($destino, $jefe)->first();
    }
}
