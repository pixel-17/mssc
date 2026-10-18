<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;

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
    public function miembrosActivos(UnidadOrganica $unidad, User $jefe)
    {
        return $unidad->miembros()->where('activo', true)->whereKeyNot($jefe->id);
    }

    /** ¿Hay que elegir un reemplazo? Sí si quedan personas o sub-unidades. */
    public function requiereReemplazo(UnidadOrganica $unidad, User $jefe): bool
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return false;
        }

        $quedaGente = $this->miembrosActivos($unidad, $jefe)->exists() || $unidad->hijos()->exists();

        return $quedaGente;
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
        if (! ($requiere ?? $this->requiereReemplazo($unidad, $jefe))) {
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
     * Quién releva a $jefe en $unidad: el elegido, o null si la unidad
     * puede quedar sin jefe.
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

        if ($elegido > 0) {
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

        if ($this->requiereReemplazo($unidad, $jefe)) {
            throw new UsuarioException(
                $jefe->nombre_completo.' encabeza «'.$unidad->nombre.'» y tiene '
                .($unidad->hijos()->exists() ? 'sub-unidades a su cargo' : 'trabajadores a su cargo')
                .': designa a su reemplazo antes de moverlo.'
            );
        }

        return null;
    }

    /**
     * Jefe actual del destino (distinto de $jefe), o vacío si no tiene.
     *
     * @return Collection<int, User>
     */
    public function jefesDelDestino(UnidadOrganica $destino, User $excepto): Collection
    {
        return collect([$destino->jefe_id ? User::find($destino->jefe_id) : null])
            ->filter()
            ->reject(fn (User $u) => (int) $u->id === (int) $excepto->id)
            ->values();
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
