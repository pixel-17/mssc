{{--
    Archivos que la papeleta guarda en el disco privado y que hasta ahora
    nadie podía abrir: adjunto inicial y foto del retorno. Los sirve
    PapeletaArchivoController con la
    misma PapeletaPolicy::view que el detalle (dueño, jefe, jefe de área,
    RR. HH.). El archivo de un sustento sigue en su propio enlace.
--}}
@php
    $archivosPapeleta = array_filter([
        'adjunto-inicial' => $papeleta->adjunto_inicial_path ? 'Adjunto inicial' : null,
        'retorno-foto' => $papeleta->retorno?->foto_path ? 'Foto del retorno' : null,
    ]);
@endphp

@if ($archivosPapeleta)
    <div class="glass-card p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Archivos adjuntos</h3>
        <ul class="text-sm text-gray-700 dark:text-tinta-100/70 space-y-1">
            @foreach ($archivosPapeleta as $tipoArchivo => $etiquetaArchivo)
                <li>
                    <a href="{{ route('papeletas.archivo', ['papeleta' => $papeleta, 'tipo' => $tipoArchivo]) }}"
                       target="_blank"
                       rel="noopener"
                       class="text-tinta-600 dark:text-tinta-300 hover:text-tinta-700 dark:hover:text-tinta-100 underline">
                        {{ $etiquetaArchivo }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
