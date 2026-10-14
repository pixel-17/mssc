<?php

namespace App\Http\Controllers\Trabajador;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de cada pantalla del trabajador. Requiere barryvdh/laravel-dompdf
 * (composer require barryvdh/laravel-dompdf).
 *
 * "Firma" = validación por el sistema: el PDF solo deja constancia de
 * quién aprobó, la hora de solicitud y la hora de retorno
 * (ver pdf/trabajador/_validacion.blade.php). Sin QR ni código.
 */
class PdfController extends Controller
{
    /** Mis papeletas (turno vigente por defecto, ?todas=1 para todas). */
    public function lista(Request $request): Response
    {
        $usuario = Auth::user();

        $papeletas = $usuario->papeletas()
            ->with(['motivo', 'sede', 'retorno', 'resueltoPorJefe', 'resueltoPorRrhh'])
            ->when(! $request->boolean('todas'), fn ($q) => $q->delTurnoVigente())
            ->latest()
            ->orderByDesc('papeletas.id')
            ->get();

        return Pdf::loadView('pdf.trabajador.lista', [
            'trabajador' => $usuario,
            'papeletas' => $papeletas,
            'todas' => $request->boolean('todas'),
        ])->download('mis-papeletas.pdf');
    }

    public function papeleta(Papeleta $papeleta): Response
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'retorno', 'trabajador', 'resueltoPorJefe', 'resueltoPorRrhh']);

        return Pdf::loadView('pdf.trabajador.papeleta', ['papeleta' => $papeleta])
            ->download('papeleta-'.str_pad((string) $papeleta->id, 4, '0', STR_PAD_LEFT).'.pdf');
    }
}
