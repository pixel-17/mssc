<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Punto único donde se guardan los archivos que sube el trabajador
 * (foto de retorno, sustento, adjunto inicial, subsanación, posthoc).
 *
 * Una foto de celular llega a 3-8 MB sin necesidad: para que RR. HH. o
 * el jefe la revisen en pantalla sobra con una versión mucho más chica,
 * así que las imágenes (jpg/jpeg/png) se reducen y se re-codifican en
 * JPEG antes de guardarse. Los PDF se guardan tal cual — comprimirlos
 * de verdad pediría Ghostscript, que no tenemos disponible acá.
 *
 * Si la compresión falla por lo que sea (EXIF raro, driver de imagen no
 * disponible, etc.) se guarda el archivo original: nunca vale la pena
 * perder la subida del trabajador por ahorrar espacio.
 */
class ArchivoSubidaService
{
    private const ANCHO_MAXIMO = 1600;

    private const ALTO_MAXIMO = 1600;

    private const CALIDAD_JPEG = 80;

    /**
     * Guarda el archivo en el disco indicado y devuelve la ruta
     * (o false si falló), igual que UploadedFile::store().
     */
    public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'local'): string|false
    {
        if ($this->esImagen($archivo)) {
            return $this->guardarImagenComprimida($archivo, $carpeta, $disco);
        }

        return $archivo->store($carpeta, $disco);
    }

    private function esImagen(UploadedFile $archivo): bool
    {
        return str_starts_with((string) $archivo->getMimeType(), 'image/');
    }

    private function guardarImagenComprimida(UploadedFile $archivo, string $carpeta, string $disco): string|false
    {
        try {
            $manager = new ImageManager(new Driver);

            // scaleDown solo achica si la imagen es más grande que el máximo;
            // una foto que ya viene chica no se toca ni pierde calidad de más.
            $imagen = $manager->decode($archivo->getRealPath())
                ->scaleDown(width: self::ANCHO_MAXIMO, height: self::ALTO_MAXIMO);

            $contenido = (string) $imagen->encode(new JpegEncoder(quality: self::CALIDAD_JPEG));

            $ruta = $carpeta.'/'.Str::random(40).'.jpg';

            if (! Storage::disk($disco)->put($ruta, $contenido)) {
                return false;
            }

            return $ruta;
        } catch (Throwable $e) {
            Log::warning('No se pudo comprimir la imagen subida, se guarda el original sin tocar.', [
                'archivo' => $archivo->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $archivo->store($carpeta, $disco);
        }
    }
}