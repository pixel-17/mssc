<?php

namespace App\Http\Controllers\Papeleta;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve los archivos de una papeleta que se guardaban pero nadie podía
 * ver: adjunto inicial y foto del retorno (el de sustentos ya lo sirve
 * SustentoArchivoController).
 *
 * Autorización: la misma PapeletaPolicy::view que el detalle (dueño,
 * jefe inmediato/adicional, jefe de área o RRHH). Disco 'local' privado,
 * nunca URL directa.
 */
class PapeletaArchivoController extends Controller
{
    public function show(Papeleta $papeleta, string $tipo): StreamedResponse
    {
        $this->authorize('view', $papeleta);

        $path = match ($tipo) {
            'adjunto-inicial' => $papeleta->adjunto_inicial_path,
            'retorno-foto' => $papeleta->retorno?->foto_path,
            default => abort(404),
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'El archivo no existe.');

        // nosniff: el navegador no debe reinterpretar un archivo subido por un usuario.
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
