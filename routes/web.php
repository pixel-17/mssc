<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Jefe\DecisionController as JefeDecisionController;
use App\Http\Controllers\Jefe\PapeletaController as JefePapeletaController;
use App\Http\Controllers\Jefe\SustentoController as JefeSustentoController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Rrhh\DecisionController as RrhhDecisionController;
use App\Http\Controllers\Rrhh\PapeletaController as RrhhPapeletaController;
use App\Http\Controllers\Rrhh\SustentoController as RrhhSustentoController;
use App\Http\Controllers\Trabajador\PapeletaController as TrabajadorPapeletaController;
use App\Http\Controllers\Trabajador\RetornoController as TrabajadorRetornoController;
use App\Http\Controllers\Trabajador\SustentoController as TrabajadorSustentoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * El catálogo de administración (sedes, motivos, turnos, feriados,
 * unidades orgánicas, configuraciones, horario de RRHH) vive
 * exclusivamente en el panel de Filament registrado en /admin
 * (AdminPanelProvider) — a propósito no hay rutas admin.* acá, para
 * no chocar con esa ruta.
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
     * Registro in-app de web push (notificaciones): cualquier rol
     * autenticado puede activar/desactivar push en su propio
     * navegador. Sin middleware de rol porque trabajador, jefe, RRHH
     * y admin reciben notificaciones por igual (ver
     * NotificarPapeletaService).
     */
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // --- Trabajador (Paso 1 y Paso 5 del flujo) ---
    Route::prefix('papeletas')->name('trabajador.papeletas.')->middleware('role:trabajador')->group(function () {
        Route::get('/', [TrabajadorPapeletaController::class, 'index'])->name('index');
        Route::get('/crear', [TrabajadorPapeletaController::class, 'create'])->name('create');
        Route::post('/', [TrabajadorPapeletaController::class, 'store'])->name('store');
        Route::get('/{papeleta}', [TrabajadorPapeletaController::class, 'show'])->name('show');
        Route::delete('/{papeleta}', [TrabajadorPapeletaController::class, 'cancelar'])->name('cancelar');

        Route::post('/{papeleta}/retorno', [TrabajadorRetornoController::class, 'store'])->name('retorno.store');
        Route::post('/sustentos/{sustento}', [TrabajadorSustentoController::class, 'store'])->name('sustento.store');
    });

    /*
     * Jefe Inmediato / Jefe de Área: NO es un rol de spatie, es
     * relacional (jefe_inmediato_id / jefe_area_id de la papeleta) —
     * por eso el único middleware es 'auth', la autorización real
     * vive en PapeletaPolicy y se aplica por papeleta.
     */
    Route::prefix('jefe')->name('jefe.')->group(function () {
        Route::get('/papeletas', [JefePapeletaController::class, 'index'])->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [JefePapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [JefeDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [JefeDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [JefeDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/reconocer-observacion-rrhh', [JefeDecisionController::class, 'reconocerObservacionRrhh'])->name('papeletas.reconocer-observacion-rrhh');
        Route::post('/papeletas/{papeleta}/retorno-manual', [JefeDecisionController::class, 'retornoManual'])->name('papeletas.retorno-manual');
        Route::post('/papeletas/{papeleta}/cerrar-sin-retorno', [JefeDecisionController::class, 'cerrarSinRetorno'])->name('papeletas.cerrar-sin-retorno');
        Route::post('/papeletas/{papeleta}/marcar-abandono', [JefeDecisionController::class, 'marcarAbandono'])->name('papeletas.marcar-abandono');

        Route::post('/sustentos/{sustento}/revisar', [JefeSustentoController::class, 'revisar'])->name('sustentos.revisar');
    });

    // --- RRHH (Paso 3 y Paso 4) ---
    Route::prefix('rrhh')->name('rrhh.')->middleware('role:rrhh')->group(function () {
        Route::get('/papeletas', [RrhhPapeletaController::class, 'index'])->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [RrhhPapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [RrhhDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [RrhhDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [RrhhDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/posthoc-aprobar', [RrhhDecisionController::class, 'posthocAprobar'])->name('papeletas.posthoc-aprobar');
        Route::post('/papeletas/{papeleta}/posthoc-observar', [RrhhDecisionController::class, 'posthocObservar'])->name('papeletas.posthoc-observar');
        Route::post('/papeletas/{papeleta}/marcar-abandono', [RrhhDecisionController::class, 'marcarAbandono'])->name('papeletas.marcar-abandono');

        Route::post('/sustentos/{sustento}/revisar', [RrhhSustentoController::class, 'revisar'])->name('sustentos.revisar');
    });
});
