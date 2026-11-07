<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\CancelarPapeletaAction;
use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\RrhhHorarioService;
use App\Actions\Papeleta\SubsanarObservacionAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Concerns\ResuelveUsuarioAutenticado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\StorePapeletaRequest;
use App\Http\Requests\Papeleta\SubsanarObservacionRequest;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Services\ArchivoSubidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * Bandeja del Trabajador (Paso 1 del flujo): crear, ver el estado de
 * sus propias papeletas, cancelar antes de AUTORIZADA_Y_CORRIENDO (en fase
 * RRHH solo si RRHH está en horario) y responder por escrito (con adjunto si el jefe lo
 * exige) a las observaciones del jefe.
 */
class PapeletaController extends Controller
{
    use ResuelveUsuarioAutenticado;

    public function create(RrhhHorarioService $horarioRrhh): View|RedirectResponse
    {
        $this->authorize('crear', Papeleta::class);

        // Con una papeleta viva no se puede crear otra: se lleva al trabajador a esa
        // papeleta, donde puede cancelarla o finalizarla, en vez de dejarlo llenar el formulario.
        if ($viva = Papeleta::vivaDe($this->usuarioAutenticado())) {
            return redirect()
                ->route('trabajador.papeletas.show', $viva)
                ->with('error', $viva->mensajeDeBloqueoParaNueva());
        }

        $motivos = Motivo::where('activo', true)->orderBy('nombre')->get();

        // Si quien crea es jefe de área y RRHH está fuera de horario, la papeleta
        // se autorizará automáticamente al enviar. Se pasa el flag a la vista para
        // mostrar el aviso de confirmación antes de que el usuario envíe el formulario.
        $usuario = $this->usuarioAutenticado();
        $avisarAutorizacionAutomatica = $usuario->esJefeDeArea() && ! $horarioRrhh->estaEnHorarioAhora();

        return view('trabajador.papeletas.create', compact('motivos', 'avisarAutorizacionAutomatica'));
    }

    public function store(StorePapeletaRequest $request, CrearPapeletaAction $action, ArchivoSubidaService $archivos): RedirectResponse
    {
        $motivo = Motivo::findOrFail($request->integer('motivo_id'));

        // El adjunto al crear es solo para los motivos que exigen justificación
        // (Salud): en los demás (p. ej. Comisión de Servicio) se ignora.
        $adjuntoPath = $motivo->requiere_sustento_en_retorno && $request->hasFile('adjunto_inicial_path')
            ? ($archivos->guardar($request->file('adjunto_inicial_path'), 'papeletas/adjuntos-iniciales') ?: null)
            : null;

        try {
            $papeleta = $action->ejecutar($this->usuarioAutenticado(), $motivo, [
                'justificacion' => $request->input('justificacion'),
                'adjunto_inicial_path' => $adjuntoPath,
                'hora_retorno_estimado' => $request->input('hora_retorno_estimado'),
            ]);
        } catch (PapeletaException $e) {
            $this->descartarAdjunto($adjuntoPath);

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->descartarAdjunto($adjuntoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Papeleta creada correctamente.');
    }

    /** Si la Action falla el archivo ya está en disco: se borra para no dejarlo huérfano. */
    private function descartarAdjunto(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('trabajador.papeletas.show', compact('papeleta'));
    }

    /**
     * Responde a una observación del jefe: siempre por escrito, con
     * adjunto si el jefe lo exigió. Igual que en SustentoController, un
     * archivo nunca debe quedar suelto en disco si la Action no llegó a
     * referenciarlo.
     */
    public function subsanar(SubsanarObservacionRequest $request, Papeleta $papeleta, SubsanarObservacionAction $action, ArchivoSubidaService $archivos): RedirectResponse
    {
        $archivoPath = $request->hasFile('archivo')
            ? ($archivos->guardar($request->file('archivo'), 'papeletas/subsanaciones') ?: null)
            : null;

        try {
            $action->ejecutar($papeleta, $this->usuarioAutenticado(), $request->input('respuesta'), $archivoPath);
        } catch (PapeletaException $e) {
            $this->descartarAdjunto($archivoPath);

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->descartarAdjunto($archivoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Respuesta enviada. Tu jefe volverá a decidir sobre la papeleta.');
    }

    public function cancelar(Papeleta $papeleta, CancelarPapeletaAction $action): RedirectResponse
    {
        $this->authorize('cancelar', $papeleta);

        try {
            $action->ejecutar($papeleta, $this->usuarioAutenticado());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('trabajador.papeletas.index')
            ->with('success', 'Papeleta cancelada.');
    }
}
