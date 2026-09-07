<?php

use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\FeriadoController;
use App\Http\Controllers\Admin\HorarioRrhhController;
use App\Http\Controllers\Admin\MotivoController;
use App\Http\Controllers\Admin\SedeController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Admin\UnidadOrganicaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Protegido por el rol 'admin' de spatie/laravel-permission.
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::resource('unidades-organicas', UnidadOrganicaController::class)
            ->except('show');
        Route::resource('sedes', SedeController::class)->except('show');
        Route::resource('motivos', MotivoController::class)->except('show');
        Route::resource('turnos', TurnoController::class)->except('show');
        Route::resource('feriados', FeriadoController::class)->except('show');

        // Sin create/store/destroy: filas fijas que solo se editan.
        Route::resource('configuraciones', ConfiguracionController::class)
            ->only(['index', 'edit', 'update']);
        Route::resource('horarios-rrhh', HorarioRrhhController::class)
            ->only(['index', 'edit', 'update']);
    });
});
