<?php

namespace App\Http\Controllers\Papeleta;

use App\Http\Controllers\Controller;
use App\Models\Sustento;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ver/descargar el archivo que el trabajador adjuntó como sustento
 * (Paso 5, motivo Salud). Sin middleware de rol a propósito, igual
 * que EmergenciaController — la autorización real vive en
 * PapeletaPolicy::verSustento (trabajador dueño, jefe inmediato o
 * RRHH). El archivo se sirve siempre desde el disco 'local' (privado,
 * fuera de /public), nunca por URL directa.
 */
class SustentoArchivoController extends Controller
{
    public function show(Sustento $sustento): StreamedResponse|Response
    {
        $this->authorize('verSustento', $sustento);

        if (! $sustento->archivo_path || ! Storage::disk('local')->exists($sustento->archivo_path)) {
            abort(404, 'El sustento todavía no tiene un archivo presentado.');
        }

        return Storage::disk('local')->response($sustento->archivo_path);
    }
}
