<?php

namespace App\Providers;

use App\Models\Sustento;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Observers\UnidadOrganicaObserver;
use App\Observers\UserObserver;
use App\Policies\PapeletaPolicy;
use App\View\Composers\NavegacionComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Por defecto, json_encode() escapa todo lo fuera de ASCII
         * (á, é, í, ó, ú, ñ, ¿, ¡... -> \u00e1, \u00e9...). Eso incluye
         * las respuestas AJAX de Livewire (cada wire:click/set hace un
         * POST que vuelve como JSON), así que cualquier assert que lea
         * el body crudo de esa respuesta (ej. TestResponse::assertSeeInOrder,
         * que Livewire no implementa y reenvía tal cual al TestResponse de
         * Laravel) nunca encuentra una palabra con tilde: está en el JSON,
         * pero como \u00e9, no como "é". JsonResponse no tiene un setter
         * estático de opciones por defecto, así que se ajusta por evento
         * en cada response ya armada. Con todo el contenido en español, no
         * tiene sentido pagar ese escape ni sufrirlo en los tests.
         *
         * No se usa `instanceof JsonResponse`: el PHPDoc de
         * RequestHandled::$response lo tipa como Illuminate\Http\Response,
         * una clase hermana (no padre) de JsonResponse, así que PHPStan
         * lo marca como instanceof.alwaysFalse aunque en runtime sí pueda
         * llegar una JsonResponse real. Se detecta por el header, que sí
         * existe en el tipo declarado.
         */
        Event::listen(function (RequestHandled $event): void {
            $esJson = str_contains((string) $event->response->headers->get('Content-Type'), 'application/json');

            if ($esJson && method_exists($event->response, 'setEncodingOptions')) {
                $event->response->setEncodingOptions(
                    JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE
                );
            }
        });

        // Detecta lazy loading (N+1) fuera de producción. Solo registra un
        // warning en el log (no lanza excepción) para no tumbar flujos que
        // aún lo hagan; para que falle en tests/local, quitar el handler.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            logger()->warning(sprintf('Lazy loading: [%s] en %s', $relation, $model::class));
        });

        User::observe(UserObserver::class);
        UnidadOrganica::observe(UnidadOrganicaObserver::class);

        /*
         * Sustento no tiene su propia Policy: las reglas de "visto
         * bueno humano" sobre un sustento (revisarSustento/verSustento)
         * viven en PapeletaPolicy a propósito (ver docblock ahí). Sin
         * este mapeo explícito, Laravel busca SustentoPolicy por
         * convención de nombre, no la encuentra, y
         * $this->authorize('revisarSustento', $sustento) deniega
         * siempre — este bind es lo que hace que esos checks
         * funcionen de verdad.
         */
        Gate::policy(Sustento::class, PapeletaPolicy::class);

        // Menús del shell (sidebar / barra inferior): ver NavegacionComposer.
        View::composer(['layouts.app', 'components.trabajador-layout'], NavegacionComposer::class);

        /*
         * sede-mapa-leaflet (JS + CSS, ~150 kB) solo lo necesita el
         * formulario de Sede, y se carga con import() dinámico para no
         * ir en el bundle general (ver resources/js/sede-mapa.js). Pero
         * @vite() de Laravel precarga en TODA página el CSS (y a veces
         * el JS) de cualquier chunk que aparezca en "dynamicImports" del
         * manifest, para evitar flash de estilos sin aplicar cuando el
         * import dinámico resuelva — eso es justamente lo que hacía que
         * estos dos archivos aparecieran en cada acción/página, aunque
         * no hubiera ningún mapa en pantalla. Se excluye ese chunk del
         * preload: solo se pedirá cuando el mapa realmente se use.
         */

    }
}