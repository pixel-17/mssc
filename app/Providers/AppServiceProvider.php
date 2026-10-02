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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
    }
}
