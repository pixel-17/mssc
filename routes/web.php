<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Jefe\DecisionController as JefeDecisionController;
use App\Http\Controllers\Jefe\PapeletaController as JefePapeletaController;
use App\Http\Controllers\Jefe\SustentoController as JefeSustentoController;
use App\Http\Controllers\Papeleta\EmergenciaController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Rrhh\DecisionController as RrhhDecisionController;
use App\Http\Controllers\Rrhh\PapeletaController as RrhhPapeletaController;
use App\Http\Controllers\Rrhh\SustentoController as RrhhSustentoController;
use App\Http\Controllers\Trabajador\PapeletaController as TrabajadorPapeletaController;
use App\Http\Controllers\Trabajador\RetornoController as TrabajadorRetornoController;
use App\Http\Controllers\Trabajador\SustentoController as TrabajadorSustentoController;
use App\Http\Controllers\Usuario\JefeAdicionalController;
use App\Http\Controllers\Usuario\UsuarioController;
use App\Http\Controllers\Usuario\VinculoController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
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

    /*
     * jefes_inmediatos_adicionales: sin middleware de rol porque puede
     * asignar/quitar un admin, el jefe de área del trabajador, o
     * cualquier jefe inmediato (automático o adicional) que el
     * trabajador ya tenga — la autorización real vive en las Actions
     * (AsignarJefeAdicionalAction / DesasignarJefeAdicionalAction).
     */
    Route::post('/trabajadores/{trabajador}/jefes-adicionales', [JefeAdicionalController::class, 'store'])->name('jefes-adicionales.store');
    Route::delete('/trabajadores/{trabajador}/jefes-adicionales/{jefe}', [JefeAdicionalController::class, 'destroy'])->name('jefes-adicionales.destroy');

    /*
     * Alta de usuarios y vinculación de jefe inmediato por Jefe de
     * Área / Jefe Inmediato (Admin NO pasa por aquí: usa el
     * UserResource de Filament). Controller y vistas ya existían
     * completos, pero nunca se habían registrado las rutas — sin
     * middleware de rol por el mismo motivo que jefes-adicionales: la
     * autorización real vive en UserPolicy.
     */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');

        Route::post('/vincular/buscar', [VinculoController::class, 'buscar'])->name('buscar');
        Route::post('/{trabajador}/vincular', [VinculoController::class, 'vincular'])->name('vincular');
    });

    /*
     * Paso 6: revisión post-hoc doble de Emergencia. Sin middleware de
     * rol por el mismo motivo que jefes-adicionales — RevisarEmergenciaAction
     * distingue jefe/RRHH internamente (hasRole('rrhh') / esJefeInmediatoDe).
     */
    Route::post('/papeletas/{papeleta}/emergencia/aprobar', [EmergenciaController::class, 'aprobar'])->name('emergencia.aprobar');
    Route::post('/papeletas/{papeleta}/emergencia/observar', [EmergenciaController::class, 'observar'])->name('emergencia.observar');

    // --- Trabajador (Paso 1 y Paso 5 del flujo) ---
    Route::prefix('papeletas')->name('trabajador.papeletas.')->middleware('role:trabajador')->group(function () {
        Route::get('/', [TrabajadorPapeletaController::class, 'index'])->name('index');
        Route::get('/crear', [TrabajadorPapeletaController::class, 'create'])->name('create');
        Route::post('/', [TrabajadorPapeletaController::class, 'store'])->name('store');
        Route::get('/{papeleta}', [TrabajadorPapeletaController::class, 'show'])->name('show');
        Route::delete('/{papeleta}', [TrabajadorPapeletaController::class, 'cancelar'])->name('cancelar');

        Route::post('/{papeleta}/retorno', [TrabajadorRetornoController::class, 'store'])->name('retorno.store');
        Route::post('/sustentos/{sustento}', [TrabajadorSustentoController::class, 'store'])->name('sustento.store');
        Route::post('/{papeleta}/emergencia/subsanar', [EmergenciaController::class, 'subsanar'])->name('emergencia.subsanar');
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