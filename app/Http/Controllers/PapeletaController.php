<?php

namespace App\Http\Controllers;

use App\Actions\Papeleta\AprobarJefeAction;
use App\Actions\Papeleta\AprobarRrhhAction;
use App\Actions\Papeleta\CancelarPapeletaAction;
use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\MarcarAbandonoSobreRetornoPendienteAction;
use App\Actions\Papeleta\MarcarRetornoAction;
use App\Actions\Papeleta\ObservarJefeAction;
use App\Actions\Papeleta\ObservarRrhhAction;
use App\Actions\Papeleta\RechazarJefeAction;
use App\Actions\Papeleta\RechazarRrhhAction;
use App\Actions\Papeleta\ReconocerObservacionRrhhAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\CrearPapeletaRequest;
use App\Http\Requests\Papeleta\RetornoRequest;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\RetornoPendienteSustento;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Capa web del flujo operativo (documento completo). Los 4 roles
 * comparten este único controller: lo que cada uno puede ver/hacer lo
 * decide PapeletaPolicy, no una rama distinta de código por rol.
 *
 * Las reglas de negocio (ventanas, exclusividad, topes, escalamiento,
 * refrigerio, etc.) viven en app/Actions/Papeleta — este controller
 * solo autoriza, junta el input del formulario, llama a la Action
 * correspondiente y traduce PapeletaException a un mensaje flash en
 * vez de un 500.
 */
class PapeletaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $misPapeletas = $user->hasRole('trabajador')
            ? Papeleta::with(['motivo', 'sede'])
                ->where('trabajador_id', $user->id)
                ->latest()
                ->paginate(15, ['*'], 'mias')
            : null;

        $pendientesComoJefe = Papeleta::with(['trabajador', 'motivo'])
            ->where(function ($query) use ($user) {
                $query->where('estado', PendienteJefe::class)
                    ->where(function ($q) use ($user) {
                        $q->whereNull('escalado_jefe_area_at')
                            ->where('jefe_inmediato_id', $user->id);
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('escalado_jefe_area_at')
                            ->where('jefe_area_id', $user->id);
                    });
            })
            ->orWhere(function ($query) use ($user) {
                $query->where('estado', ObservadaPorRrhh::class)
                    ->where('jefe_inmediato_id', $user->id);
            })
            ->latest()
            ->paginate(15, ['*'], 'jefe');

        $pendientesRrhh = null;
        $posthocPendientes = null;

        if ($user->hasRole('rrhh')) {
            $pendientesRrhh = Papeleta::with(['trabajador', 'motivo'])
                ->where('estado', PendienteRrhh::class)
                ->latest()
                ->paginate(15, ['*'], 'rrhh');

            $posthocPendientes = Papeleta::with(['trabajador', 'motivo'])
                ->where('autorizado_con_rrhh_fuera_horario', true)
                ->where('revision_posthoc_estado', 'pendiente')
                ->latest()
                ->paginate(15, ['*'], 'posthoc');
        }

        $sustentosPendientes = Papeleta::with(['trabajador', 'motivo', 'sustentos'])
            ->where('estado', RetornoPendienteSustento::class)
            ->where(function ($query) use ($user) {
                $query->where('jefe_inmediato_id', $user->id)
                    ->orWhere('jefe_area_id', $user->id);

                if ($user->hasRole('rrhh')) {
                    $query->orWhere('id', '>', 0);
                }
            })
            ->latest()
            ->paginate(15, ['*'], 'sustento');

        return view('papeletas.index', [
            'misPapeletas' => $misPapeletas,
            'pendientesComoJefe' => $pendientesComoJefe,
            'pendientesRrhh' => $pendientesRrhh,
            'posthocPendientes' => $posthocPendientes,
            'sustentosPendientes' => $sustentosPendientes,
        ]);
    }

    public function create(): View
    {
        $this->authorize('crear', Papeleta::class);

        $motivos = Motivo::where('activo', true)->orderBy('nombre')->get();

        return view('papeletas.create', compact('motivos'));
    }

    public function store(CrearPapeletaRequest $request, CrearPapeletaAction $action): RedirectResponse
    {
        $motivo = Motivo::findOrFail($request->integer('motivo_id'));

        $datos = ['justificacion' => $request->input('justificacion')];

        if ($request->hasFile('adjunto')) {
            $datos['adjunto_inicial_path'] = $request->file('adjunto')->store('papeletas/adjuntos', 'public');
        }

        return $this->intentar(
            fn () => $action->ejecutar($request->user(), $motivo, $datos),
            'Papeleta creada.'
        );
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load([
            'trabajador', 'motivo', 'motivoOriginal', 'sede',
            'jefeInmediato', 'jefeArea', 'resueltoPorJefe', 'resueltoPorRrhh',
            'rechazadaPor', 'revisionPosthocPor',
            'retorno.marcadoManualPor', 'sustentos.revisadoPor',
            'historial' => fn ($query) => $query->latest('id'),
            'historial.actor', 'historial.motivoAnterior', 'historial.motivoNuevo',
        ]);

        return view('papeletas.show', compact('papeleta'));
    }

    public function cancelar(Request $request, Papeleta $papeleta, CancelarPapeletaAction $action): RedirectResponse
    {
        $this->authorize('cancelar', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user()),
            'Papeleta cancelada.',
            $papeleta
        );
    }

    // -- Jefe (inmediato o de área; la Policy resuelve cuál corresponde) --

    public function aprobarJefe(Request $request, Papeleta $papeleta, AprobarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $this->actorTipoJefe($papeleta)),
            'Papeleta aprobada.',
            $papeleta
        );
    }

    public function rechazarJefe(ComentarioRequest $request, Papeleta $papeleta, RechazarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario'), $this->actorTipoJefe($papeleta)),
            'Papeleta rechazada.',
            $papeleta
        );
    }

    public function observarJefe(ComentarioRequest $request, Papeleta $papeleta, ObservarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario'), $this->actorTipoJefe($papeleta)),
            'Observación registrada.',
            $papeleta
        );
    }

    public function reconocerObservacionRrhh(ComentarioRequest $request, Papeleta $papeleta, ReconocerObservacionRrhhAction $action): RedirectResponse
    {
        $this->authorize('reconocerObservacionRrhh', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario')),
            'Observación de RRHH reconocida. La papeleta vuelve a tu bandeja.',
            $papeleta
        );
    }

    public function retornoManual(ComentarioRequest $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $this->authorize('marcarRetornoManual', $papeleta);

        return $this->intentar(
            fn () => $action->manual($papeleta, $request->user(), $request->input('comentario')),
            'Retorno manual registrado (falla de conectividad). RRHH será alertado.',
            $papeleta
        );
    }

    // -- RRHH --

    public function aprobarRrhh(Request $request, Papeleta $papeleta, AprobarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user()),
            'Papeleta autorizada.',
            $papeleta
        );
    }

    public function rechazarRrhh(ComentarioRequest $request, Papeleta $papeleta, RechazarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario')),
            'Papeleta rechazada.',
            $papeleta
        );
    }

    public function observarRrhh(ComentarioRequest $request, Papeleta $papeleta, ObservarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario')),
            'Observación enviada al jefe.',
            $papeleta
        );
    }

    public function posthocAprobar(Request $request, Papeleta $papeleta, RevisionPosthocAction $action): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        return $this->intentar(
            fn () => $action->aprobar($papeleta, $request->user()),
            'Revisión post-hoc aprobada.',
            $papeleta
        );
    }

    public function posthocObservar(ComentarioRequest $request, Papeleta $papeleta, RevisionPosthocAction $action): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        return $this->intentar(
            fn () => $action->observar($papeleta, $request->user(), $request->input('comentario')),
            'Revisión post-hoc observada.',
            $papeleta
        );
    }

    // -- Cierre / abandono sobre casos ya en curso --

    public function marcarAbandono(ComentarioRequest $request, Papeleta $papeleta, MarcarAbandonoSobreRetornoPendienteAction $action): RedirectResponse
    {
        $this->authorize('decidirCierreOAbandono', $papeleta);

        return $this->intentar(
            fn () => $action->ejecutar($papeleta, $request->user(), $request->input('comentario')),
            'Marcado como abandono no marcado.',
            $papeleta
        );
    }

    /** Comisión de Servicio sin retorno físico: visto bueno humano de cierre. */
    public function cerrarSinRetorno(Request $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $this->authorize('decidirCierreOAbandono', $papeleta);

        return $this->intentar(
            fn () => $action->cerrarSinRetornoFisico($papeleta, $request->user()),
            'Papeleta cerrada sin retorno físico (comisión de servicio).',
            $papeleta
        );
    }

    // -- Trabajador: retorno --

    public function retorno(RetornoRequest $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $this->authorize('marcarRetorno', $papeleta);

        $datos = [
            'foto_path' => $request->file('foto')->store('papeletas/retornos', 'public'),
            'latitud' => $request->input('latitud'),
            'longitud' => $request->input('longitud'),
        ];

        return $this->intentar(
            fn () => $action->normal($papeleta, $request->user(), $datos),
            'Retorno registrado.',
            $papeleta
        );
    }

    private function actorTipoJefe(Papeleta $papeleta): string
    {
        return $papeleta->escalado_jefe_area_at !== null ? 'jefe_area' : 'jefe_inmediato';
    }

    /**
     * Ejecuta la Action dentro del closure y traduce PapeletaException
     * a un mensaje flash de error, en vez de repetir el mismo
     * try/catch en cada método público. $papeleta es el destino del
     * redirect tanto en éxito como en error (para volver siempre a la
     * misma ficha); si es null (creación), vuelve con back() en error
     * o a la ficha recién creada en éxito.
     */
    private function intentar(Closure $callback, string $mensajeExito, ?Papeleta $papeleta = null): RedirectResponse
    {
        try {
            $resultado = $callback();
        } catch (PapeletaException $e) {
            return $papeleta
                ? redirect()->route('papeletas.show', $papeleta)->with('error', $e->getMessage())
                : back()->withInput()->with('error', $e->getMessage());
        }

        $destino = $papeleta ?? $resultado;

        return redirect()->route('papeletas.show', $destino)->with('status', $mensajeExito);
    }
}
