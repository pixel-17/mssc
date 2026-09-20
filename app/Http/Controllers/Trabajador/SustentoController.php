<?php

namespace App\Http\Controllers\Trabajador;

use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\SubirSustentoRequest;
use App\Models\Sustento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Paso 5, motivo Salud: el trabajador sube el archivo de sustento.
 * A propósito NO es un Action de app/Actions/Papeleta — subir el
 * archivo nunca cierra el caso por sí solo, solo dejar constancia de
 * que ya se presentó (ver RevisarSustentoAction, que sí vive en
 * Actions porque esa parte SÍ es una decisión humana sobre el flujo).
 */
class SustentoController extends Controller
{
    public function store(SubirSustentoRequest $request, Sustento $sustento): RedirectResponse
    {
        if ($sustento->estado !== 'pendiente') {
            return back()->with('error', 'Este sustento ya fue presentado o revisado.');
        }

        $archivoPath = $request->file('archivo')->store('papeletas/sustentos', 'local');

        try {
            DB::transaction(function () use ($sustento, $archivoPath) {
                // Relectura bajo lock: dos envíos casi simultáneos (doble clic, dos
                // pestañas) pasaban ambos el chequeo de arriba y el segundo pisaba
                // la ruta del primero, dejando ese archivo suelto en el disco.
                /** @var Sustento $actual */
                $actual = Sustento::whereKey($sustento->id)->lockForUpdate()->firstOrFail();

                if ($actual->estado !== 'pendiente') {
                    throw new PapeletaException('Este sustento ya fue presentado o revisado.');
                }

                // Si RR. HH. observó un sustento anterior, `archivo_path` aún apunta
                // al archivo observado y este update lo reemplaza. A propósito NO se
                // borra aquí: queda como huérfano recuperable y lo retira
                // `php artisan archivos:huerfanos --borrar` pasado su plazo de gracia.
                $actual->update([
                    'archivo_path' => $archivoPath,
                    'estado' => 'presentado',
                    'presentado_at' => now(),
                ]);
            });
        } catch (PapeletaException $e) {
            // Nunca llegó a referenciarse: el archivo recién subido no debe quedar en disco.
            Storage::disk('local')->delete($archivoPath);

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Storage::disk('local')->delete($archivoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $sustento->papeleta_id)
            ->with('success', 'Sustento presentado, queda a la espera de revisión.');
    }
}
