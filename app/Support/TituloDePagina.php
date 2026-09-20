<?php

namespace App\Support;

/**
 * Título de la pestaña del navegador (<title>). Lo leen los lectores de
 * pantalla al entrar a cada página y es lo que se ve en el historial y
 * en las pestañas: antes todas se llamaban solo "MSSC".
 *
 * Prioridad:
 *  1. $title  -> lo pone Livewire con #[Title('...')] en la clase del componente.
 *  2. $titulo -> prop explícita del layout (x-trabajador-layout titulo="...").
 *  3. $header -> el slot "header" de las páginas Blade con x-app-layout; se
 *     toma el primer <h1>/<h2> (o, si no hay, todo el texto del slot).
 */
class TituloDePagina
{
    public static function resolver(?string $title = null, ?string $titulo = null, mixed $header = null): ?string
    {
        foreach ([$title, $titulo] as $directo) {
            if (is_string($directo) && trim($directo) !== '') {
                return trim($directo);
            }
        }

        if ($header === null) {
            return null;
        }

        $html = (string) $header;

        if (preg_match('/<h[12]\b[^>]*>(.*?)<\/h[12]>/s', $html, $coincidencia)) {
            $html = $coincidencia[1];
        }

        $texto = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

        return $texto !== '' ? $texto : null;
    }
}
