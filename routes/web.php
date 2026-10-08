<?php

use App\Http\Controllers\Jefe\DecisionController as JefeDecisionController;
use App\Http\Controllers\Jefe\PapeletaController as JefePapeletaController;
use App\Http\Controllers\Papeleta\PapeletaArchivoController;
use App\Http\Controllers\Papeleta\SustentoArchivoController;
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
 * Todo el catálogo de administración (Sedes, Motivos, Turnos,
 * Unidades orgánicas, Configuraciones, Horario de RRHH,
 * Usuarios) ya vive en Blade + Livewire puro, uno por uno, en los
 * bloques de abajo.
 */
/*
 * Sedes: catálogo de administración en Blade + Livewire puro
 * (App\Livewire\Sedes\*).
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('sedes')->name('sedes.')->group(function () {
    Route::get('/', \App\Livewire\Sedes\SedeIndex::class)->name('index');
    Route::get('/crear', \App\Livewire\Sedes\SedeForm::class)->name('crear');
    Route::get('/{sede}/editar', \App\Livewire\Sedes\SedeForm::class)->name('editar');
});

/*
 * Motivos: catálogo de administración en Blade + Livewire puro
 * (App\Livewire\Motivos\*), mismo patrón que Sedes.
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('motivos')->name('motivos.')->group(function () {
    Route::get('/', \App\Livewire\Motivos\MotivoIndex::class)->name('index');
    Route::get('/crear', \App\Livewire\Motivos\MotivoForm::class)->name('crear');
    Route::get('/{motivo}/editar', \App\Livewire\Motivos\MotivoForm::class)->name('editar');
});

/*
 * Turnos (catálogo de admin): solo DEFINE las horas de cada turno
 * (Mañana, Tarde, Noche, Día). No programa a ningún trabajador: eso lo
 * hacen los jefes en el calendario de equipo (ver más abajo).
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('turnos')->name('turnos.')->group(function () {
    Route::get('/', \App\Livewire\Turnos\DefinicionTurnos::class)->name('index');

    // Programar horarios por usuario: el admin busca a la persona y le arma su calendario.
    Route::get('/administrar', \App\Livewire\Turnos\ProgramacionAdmin::class)->name('administrar');
});

/*
 * Configuración mensual de turno (ciclo 6x1 con arrastre automático
 * al mes siguiente): a diferencia del CRUD de arriba, NO es
 * role:admin — la carga también la hace el Jefe Inmediato/Área del
 * trabajador (ver User::puedeGestionarTurnoDe). La autorización fina
 * por trabajador se resuelve dentro del componente, no aquí.
 *
 * Calendario (vista de equipo/individual): mismo criterio, NO
 * role:admin — la autorización fina vive dentro de cada componente
 * (CalendarioEquipoIndex resuelve solo por relación; CalendarioIndividualIndex
 * aborta con 403 si no es Admin ni el propio trabajador).
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('turnos')->name('turnos.')->group(function () {
    Route::get('/configuracion/{trabajador}', \App\Livewire\Turnos\ConfiguracionTurnoForm::class)->name('configuracion');

    // Programación día por día (solo 728): la autorización fina vive en el componente.
    Route::get('/programacion/{trabajador}', \App\Livewire\Turnos\ProgramacionMensual::class)->name('programacion');
    Route::get('/programacion-equipo', \App\Livewire\Turnos\ProgramacionEquipo::class)->name('programacion.equipo');

    Route::get('/calendario', \App\Livewire\Turnos\CalendarioEquipoIndex::class)->name('calendario.equipo');
    Route::get('/calendario/mio', \App\Livewire\Turnos\CalendarioIndividualIndex::class)->name('calendario.individual');
    Route::get('/calendario/{trabajador}', \App\Livewire\Turnos\CalendarioIndividualIndex::class)->name('calendario.individual-de');
});

/*
 * Organigrama visual y editable (mover trabajadores y jefes, editar unidades
 * y personas): admin ve y edita todo; el Jefe de Área, su unidad y lo que
 * cuelga de ella. La autorización vive en el componente y en las Actions.
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->get('/organigrama', \App\Livewire\Organigrama\OrganigramaArbol::class)->name('organigrama.index');

/*
 * Unidades orgánicas: quinto recurso migrado — Blade + Livewire puro
 * (App\Livewire\UnidadesOrganicas\*).
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('unidades-organicas')->name('unidades-organicas.')->group(function () {
    Route::get('/', \App\Livewire\UnidadesOrganicas\UnidadOrganicaIndex::class)->name('index');
    Route::get('/crear', \App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::class)->name('crear');
    Route::get('/{unidad}/editar', \App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::class)->name('editar');
});

/*
 * Configuraciones: sexto recurso migrado — Blade + Livewire puro
 * (App\Livewire\Configuraciones\*). Sin ruta de creación: son filas
 * fijas sembradas por ConfiguracionSeeder, solo se editan.
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('configuraciones')->name('configuraciones.')->group(function () {
    Route::get('/', \App\Livewire\Configuraciones\ConfiguracionIndex::class)->name('index');
    Route::get('/{configuracion}/editar', \App\Livewire\Configuraciones\ConfiguracionForm::class)->name('editar');
});

/*
 * Usuarios (gestión de admin): octavo y último recurso migrado —
 * Blade + Livewire puro (App\Livewire\Usuarios\*). Prefijo
 * 'usuarios-admin' (no 'usuarios') para no chocar con las rutas
 * 'usuarios.*' de más abajo, que son la vía de alta para Jefe de
 * Área / Jefe Inmediato con reglas propias (UserPolicy) — control
 * total sin esas restricciones de área es exclusivo de admin.
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('usuarios-admin')->name('usuarios-admin.')->group(function () {
    Route::get('/', \App\Livewire\Usuarios\UsuarioAdminIndex::class)->name('index');
    Route::get('/crear', \App\Livewire\Usuarios\UsuarioAdminForm::class)->name('crear');
    Route::get('/{usuario}/editar', \App\Livewire\Usuarios\UsuarioAdminForm::class)->name('editar');
});

/*
 * Papeletas para el admin: TODAS, de cualquier estado, SOLO LECTURA.
 * El admin no decide ni aprueba (eso es de jefe y RRHH).
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/papeletas', \App\Livewire\Papeletas\AdminPapeletasIndex::class)->name('papeletas.index');
    Route::get('/papeletas/{papeleta}', [\App\Http\Controllers\Admin\PapeletaController::class, 'show'])->name('papeletas.show');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:admin',
])->get('/catalogos', \App\Livewire\Catalogos\CatalogoIndex::class)->name('catalogos.index');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', \App\Livewire\DashboardIndex::class)->name('dashboard');

    /*
     * Reporte de cierre de mes (no confundir con el dashboard "en
     * vivo" de arriba): admin/RRHH ven todo el personal, jefe solo su
     * equipo — mismo criterio de acceso que el dashboard, aplicado en
     * HorasAcumuladasIndex::mount(). Sin middleware de rol por el
     * mismo motivo que 'jefe' en el resto del archivo: "jefe" no es un
     * rol de Spatie.
     */
    Route::get('/reportes/horas-acumuladas', \App\Livewire\Reportes\HorasAcumuladasIndex::class)
        ->name('reportes.horas-acumuladas');

    Route::get('/reportes/trabajador', \App\Livewire\Reportes\TrabajadorHistorialIndex::class)
        ->name('reportes.trabajador-historial');

    Route::get('/reportes/sustentos', \App\Livewire\Reportes\SustentosIndex::class)
        ->name('reportes.sustentos');

    // Reportes y listas solo para RR. HH. (y admin): lo que RR. HH. decidió,
    // el resumen mensual y los abandonos. A diferencia de los reportes de
    // arriba, un jefe NO entra aquí.
    Route::middleware('role:rrhh|admin')->group(function () {
        Route::get('/reportes/decisiones-rrhh', \App\Livewire\Reportes\DecisionesRrhhIndex::class)
            ->name('reportes.decisiones-rrhh');
        Route::get('/reportes/resumen-papeletas', \App\Livewire\Reportes\ResumenPapeletasIndex::class)
            ->name('reportes.resumen-papeletas');

        // Lista de abandonos y comisiones cerradas sin retorno (solo lectura).
        Route::get('/rrhh/abandonos', \App\Livewire\Papeletas\RrhhAbandonos::class)->name('rrhh.abandonos.index');
    });

    /*
     * Pantalla opcional de "actualiza tu contraseña" para quien entra
     * por primera vez con la contraseña = DNI que le asignó
     * CrearUsuarioAction/UsuarioAdminForm. A esta ruta la trae
     * RedirigirSiDebeActualizarPassword (bootstrap/app.php); ella
     * misma apaga la bandera al guardar o al omitir.
     */
    Route::get('/actualizar-password-inicial', \App\Livewire\Auth\ActualizarPasswordInicial::class)
        ->name('password.actualizar-inicial');

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
     * Alta, edición y vinculación de jefe inmediato por Jefe de Área /
     * Jefe Inmediato (Admin NO pasa por aquí: gestiona usuarios desde el
     * panel de administración / el organigrama). Sin middleware de rol por el mismo motivo que
     * jefes-adicionales: la autorización real vive en UserPolicy
     * (ver UserPolicy::editar() para el alcance de edit/update).
     */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{trabajador}/editar', [UsuarioController::class, 'edit'])->name('edit');
        Route::put('/{trabajador}', [UsuarioController::class, 'update'])->name('update');

        Route::post('/vincular/buscar', [VinculoController::class, 'buscar'])->name('buscar');
        Route::post('/{trabajador}/vincular', [VinculoController::class, 'vincular'])->name('vincular');
    });

    /*
     * Ver/descargar el archivo de un sustento (Paso 5/8): sin
     * middleware de rol (igual que jefes-adicionales) — la
     * autorización real vive en PapeletaPolicy::verSustento
     * (trabajador dueño, jefe inmediato o RRHH).
     */
    Route::get('/papeletas/sustentos/{sustento}/archivo', [SustentoArchivoController::class, 'show'])->name('sustentos.archivo');

    /*
     * Archivos guardados en la propia papeleta (adjunto inicial, foto del
     * retorno): sin middleware de
     * rol, igual que sustentos.archivo — la autorización real vive en
     * PapeletaPolicy::view (PapeletaArchivoController).
     */
    Route::get('/papeletas/{papeleta}/archivo/{tipo}', [PapeletaArchivoController::class, 'show'])
        ->whereIn('tipo', ['adjunto-inicial', 'retorno-foto', 'justificacion-observacion', 'respuesta-posthoc'])
        ->name('papeletas.archivo');

    // --- Trabajador (Paso 1 y Paso 5 del flujo) ---
    Route::prefix('papeletas')->name('trabajador.papeletas.')->middleware('role:trabajador')->group(function () {
        Route::get('/', \App\Livewire\Papeletas\TrabajadorIndex::class)->name('index');
        Route::get('/crear', [TrabajadorPapeletaController::class, 'create'])->name('create');
        Route::post('/', [TrabajadorPapeletaController::class, 'store'])->name('store');
        Route::get('/{papeleta}', [TrabajadorPapeletaController::class, 'show'])->name('show');
        Route::delete('/{papeleta}', [TrabajadorPapeletaController::class, 'cancelar'])->name('cancelar');

        Route::post('/{papeleta}/subsanar', [TrabajadorPapeletaController::class, 'subsanar'])->name('subsanar');
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
        Route::get('/papeletas', \App\Livewire\Papeletas\JefeIndex::class)->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [JefePapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [JefeDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [JefeDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [JefeDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/reconocer-observacion-rrhh', [JefeDecisionController::class, 'reconocerObservacionRrhh'])->name('papeletas.reconocer-observacion-rrhh');
        Route::post('/papeletas/{papeleta}/responder-posthoc', [JefeDecisionController::class, 'responderPosthoc'])->name('papeletas.responder-posthoc');
        Route::post('/papeletas/{papeleta}/retorno-manual', [JefeDecisionController::class, 'retornoManual'])->name('papeletas.retorno-manual');
        Route::post('/papeletas/{papeleta}/cerrar-sin-retorno', [JefeDecisionController::class, 'cerrarSinRetorno'])->name('papeletas.cerrar-sin-retorno');
        Route::post('/papeletas/{papeleta}/marcar-abandono', [JefeDecisionController::class, 'marcarAbandono'])->name('papeletas.marcar-abandono');

    });

    // --- RRHH (Paso 3 y Paso 4) ---
    Route::prefix('rrhh')->name('rrhh.')->middleware('role:rrhh')->group(function () {
        Route::get('/papeletas', \App\Livewire\Papeletas\RrhhIndex::class)->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [RrhhPapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [RrhhDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [RrhhDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [RrhhDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/posthoc-aprobar', [RrhhDecisionController::class, 'posthocAprobar'])->name('papeletas.posthoc-aprobar');
        Route::post('/papeletas/{papeleta}/posthoc-observar', [RrhhDecisionController::class, 'posthocObservar'])->name('papeletas.posthoc-observar');
        Route::post('/papeletas/{papeleta}/marcar-abandono', [RrhhDecisionController::class, 'marcarAbandono'])->name('papeletas.marcar-abandono');
        Route::post('/papeletas/{papeleta}/corregir', [RrhhDecisionController::class, 'corregir'])->name('papeletas.corregir');

        Route::post('/sustentos/{sustento}/revisar', [RrhhSustentoController::class, 'revisar'])->name('sustentos.revisar');
    });
});