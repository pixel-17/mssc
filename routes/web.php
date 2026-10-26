<?php

use App\Http\Controllers\Admin\PapeletaController;
use App\Http\Controllers\Jefe\DecisionController as JefeDecisionController;
use App\Http\Controllers\Jefe\PapeletaController as JefePapeletaController;
use App\Http\Controllers\Papeleta\PapeletaArchivoController;
use App\Http\Controllers\Papeleta\SustentoArchivoController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Rrhh\DecisionController as RrhhDecisionController;
use App\Http\Controllers\Rrhh\PapeletaController as RrhhPapeletaController;
use App\Http\Controllers\Rrhh\SustentoController as RrhhSustentoController;
use App\Http\Controllers\Trabajador\PapeletaController as TrabajadorPapeletaController;
use App\Http\Controllers\Trabajador\PdfController as TrabajadorPdfController;
use App\Http\Controllers\Trabajador\RetornoController as TrabajadorRetornoController;
use App\Http\Controllers\Trabajador\SustentoController as TrabajadorSustentoController;
use App\Http\Controllers\Usuario\UsuarioController;
use App\Livewire\Auth\ActualizarPasswordInicial;
use App\Livewire\Catalogos\CatalogoIndex;
use App\Livewire\Configuraciones\ConfiguracionForm;
use App\Livewire\Configuraciones\ConfiguracionIndex;
use App\Livewire\DashboardIndex;
use App\Livewire\Motivos\MotivoForm;
use App\Livewire\Motivos\MotivoIndex;
use App\Livewire\Organigrama\OrganigramaArbol;
use App\Livewire\Papeletas\AdminPapeletasIndex;
use App\Livewire\Papeletas\JefeIndex;
use App\Livewire\Papeletas\RrhhAbandonos;
use App\Livewire\Papeletas\RrhhIndex;
use App\Livewire\Papeletas\TrabajadorIndex;
use App\Livewire\Reportes\DecisionesRrhhIndex;
use App\Livewire\Reportes\ResumenPapeletasIndex;
use App\Livewire\Reportes\SustentosIndex;
use App\Livewire\Reportes\TrabajadorHistorialIndex;
use App\Livewire\Sedes\SedeForm;
use App\Livewire\Sedes\SedeIndex;
use App\Livewire\Turnos\CalendarioEquipoIndex;
use App\Livewire\Turnos\CalendarioIndividualIndex;
use App\Livewire\Turnos\ConfiguracionTurnoForm;
use App\Livewire\Turnos\DefinicionTurnos;
use App\Livewire\Turnos\ProgramacionAdmin;
use App\Livewire\Turnos\ProgramacionEquipo;
use App\Livewire\Turnos\ProgramacionMensual;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaIndex;
use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Livewire\Usuarios\UsuarioAdminIndex;
use App\Livewire\Usuarios\UsuarioForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Pilas de middleware compartidas por casi todos los grupos de abajo
// (antes el mismo array se repetía 11 veces).
$autenticado = ['auth:sanctum', config('jetstream.auth_session'), 'verified'];
$soloAdmin = [...$autenticado, 'role:admin'];

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
 * Todo el catálogo de administración (Sedes, Motivos, Turnos,
 * Unidades orgánicas, Configuraciones,
 * Usuarios) ya vive en Blade + Livewire puro, uno por uno, en los
 * bloques de abajo.
 */
/*
 * Sedes: catálogo de administración en Blade + Livewire puro
 * (App\Livewire\Sedes\*).
 */
Route::middleware($soloAdmin)->prefix('sedes')->name('sedes.')->group(function () {
    Route::get('/', SedeIndex::class)->name('index');
    Route::get('/crear', SedeForm::class)->name('crear');
    Route::get('/{sede}/editar', SedeForm::class)->name('editar');
});

/*
 * Motivos: catálogo de administración en Blade + Livewire puro
 * (App\Livewire\Motivos\*), mismo patrón que Sedes.
 */
Route::middleware($soloAdmin)->prefix('motivos')->name('motivos.')->group(function () {
    Route::get('/', MotivoIndex::class)->name('index');
    Route::get('/crear', MotivoForm::class)->name('crear');
    Route::get('/{motivo}/editar', MotivoForm::class)->name('editar');
});

/*
 * Turnos (catálogo de admin): solo DEFINE las horas de cada turno
 * (Mañana, Tarde, Noche, Día). No programa a ningún trabajador: eso lo
 * hacen los jefes en el calendario de equipo (ver más abajo).
 */
Route::middleware($soloAdmin)->prefix('turnos')->name('turnos.')->group(function () {
    Route::get('/', DefinicionTurnos::class)->name('index');

    // Programar horarios por usuario: el admin busca a la persona y le arma su calendario.
    Route::get('/administrar', ProgramacionAdmin::class)->name('administrar');
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
Route::middleware($autenticado)->prefix('turnos')->name('turnos.')->group(function () {
    Route::get('/configuracion/{trabajador}', ConfiguracionTurnoForm::class)->name('configuracion');

    // Programación día por día (solo 728): la autorización fina vive en el componente.
    Route::get('/programacion/{trabajador}', ProgramacionMensual::class)->name('programacion');
    Route::get('/programacion-equipo', ProgramacionEquipo::class)->name('programacion.equipo');

    Route::get('/calendario', CalendarioEquipoIndex::class)->name('calendario.equipo');
    Route::get('/calendario/mio', CalendarioIndividualIndex::class)->name('calendario.individual');
    Route::get('/calendario/{trabajador}', CalendarioIndividualIndex::class)->name('calendario.individual-de');
});

/*
 * Organigrama visual y editable (mover trabajadores y jefes, editar unidades
 * y personas): admin ve y edita todo; el Jefe de Área, su unidad y lo que
 * cuelga de ella. La autorización vive en el componente y en las Actions.
 */
Route::middleware($autenticado)->get('/organigrama', OrganigramaArbol::class)->name('organigrama.index');

/*
 * Unidades orgánicas: Blade + Livewire puro
 * (App\Livewire\UnidadesOrganicas\*).
 */
Route::middleware($soloAdmin)->prefix('unidades-organicas')->name('unidades-organicas.')->group(function () {
    Route::get('/', UnidadOrganicaIndex::class)->name('index');
    Route::get('/crear', UnidadOrganicaForm::class)->name('crear');
    Route::get('/{unidad}/editar', UnidadOrganicaForm::class)->name('editar');
});

/*
 * Configuraciones: Blade + Livewire puro
 * (App\Livewire\Configuraciones\*). Sin ruta de creación: son filas
 * fijas sembradas por ConfiguracionSeeder, solo se editan.
 */
Route::middleware($soloAdmin)->prefix('configuraciones')->name('configuraciones.')->group(function () {
    Route::get('/', ConfiguracionIndex::class)->name('index');
    Route::get('/{configuracion}/editar', ConfiguracionForm::class)->name('editar');
});

/*
 * Usuarios (gestión de admin): Blade + Livewire puro (App\Livewire\Usuarios\*). Prefijo
 * 'usuarios-admin' (no 'usuarios') para no chocar con las rutas
 * 'usuarios.*' de más abajo, que son la vía de alta para Jefe de
 * Área / Jefe Inmediato con reglas propias (UserPolicy) — control
 * total sin esas restricciones de área es exclusivo de admin.
 */
/*
 * Listado del admin. Crear y editar comparten ruta con los jefes (usuarios.crear /
 * usuarios.editar): UsuarioForm calcula el alcance (admin | area | equipo) en mount().
 */
Route::middleware($soloAdmin)->get('/usuarios-admin', UsuarioAdminIndex::class)->name('usuarios-admin.index');
Route::middleware($soloAdmin)->get('/usuarios-admin/crear', UsuarioAdminForm::class)->name('usuarios-admin.crear');
Route::middleware($soloAdmin)->get('/usuarios-admin/{usuario}/editar', UsuarioAdminForm::class)->name('usuarios-admin.editar');

/*
 * Papeletas para el admin y RR. HH.: TODAS, de cualquier estado, SOLO
 * LECTURA (ni admin ni RRHH deciden ni aprueban desde aquí: la
 * decisión sigue siendo de jefe y de la bandeja de RRHH). Mantiene el
 * prefijo/nombre 'admin.*' por compatibilidad con lo ya existente
 * (NavegacionComposer, tests); RRHH entra con el mismo componente,
 * mismo criterio que 'rrhh.abandonos.index' (ver más abajo).
 */
Route::middleware([...$autenticado, 'role:admin|rrhh'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/papeletas', AdminPapeletasIndex::class)->name('papeletas.index');
    Route::get('/papeletas/{papeleta}', [PapeletaController::class, 'show'])->name('papeletas.show');
});

Route::middleware($soloAdmin)->get('/catalogos', CatalogoIndex::class)->name('catalogos.index');

Route::middleware($autenticado)->group(function () {
    Route::get('/dashboard', DashboardIndex::class)->name('dashboard');

    /*
     * Reporte de cierre de mes (no confundir con el dashboard "en
     * vivo" de arriba), con dos pestañas: "resumen" (conteos del
     * proceso, solo RR. HH./admin) y "horas" (horas fuera de sede por
     * trabajador, para descuento de planilla — admin/RRHH ven todo el
     * personal, jefe solo su equipo). El componente mismo recorta qué
     * pestaña puede ver cada quien (ResumenPapeletasIndex::puedeVerResumen());
     * por eso vive fuera del grupo role:rrhh|admin, igual que antes
     * vivía HorasAcumuladasIndex. Sin middleware de rol por el mismo
     * motivo que 'jefe' en el resto del archivo: "jefe" no es un rol
     * de Spatie.
     */
    Route::get('/reportes/resumen-papeletas', ResumenPapeletasIndex::class)
        ->name('reportes.resumen-papeletas');

    Route::get('/reportes/trabajador', TrabajadorHistorialIndex::class)
        ->name('reportes.trabajador-historial');

    Route::get('/reportes/sustentos', SustentosIndex::class)
        ->name('reportes.sustentos');

    // Reportes y listas solo para RR. HH. (y admin). A diferencia de los
    // reportes de arriba, un jefe NO entra aquí.
    Route::middleware('role:rrhh|admin')->group(function () {
        Route::get('/reportes/decisiones-rrhh', DecisionesRrhhIndex::class)
            ->name('reportes.decisiones-rrhh');
        // Lista de abandonos y comisiones cerradas sin retorno (solo lectura).
        Route::get('/rrhh/abandonos', RrhhAbandonos::class)->name('rrhh.abandonos.index');
    });

    /*
     * Pantalla obligatoria de "actualiza tu contraseña" para quien entra
     * por primera vez con la contraseña = DNI que le asignó
     * CrearUsuarioAction/UsuarioForm. A esta ruta la trae
     * RedirigirSiDebeActualizarPassword (bootstrap/app.php); ella
     * misma apaga la bandera al guardar la contraseña nueva.
     */
    Route::get('/actualizar-password-inicial', ActualizarPasswordInicial::class)
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
     * Alta y edición de usuarios por Jefe de Área /
     * Jefe Inmediato (Admin NO pasa por aquí: gestiona usuarios desde el
     * panel de administración / el organigrama). Sin middleware de rol: la autorización real vive en UserPolicy
     * (ver UserPolicy::editar() para el alcance de edit/update).
     */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', UsuarioForm::class)->name('crear');
        Route::get('/{usuario}/editar', UsuarioForm::class)->name('editar');
    });

    /*
     * Ver/descargar el archivo de un sustento (Paso 5/8): sin
     * middleware de rol — la
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
        Route::get('/', TrabajadorIndex::class)->name('index');
        Route::get('/crear', [TrabajadorPapeletaController::class, 'create'])->name('create');
        Route::post('/', [TrabajadorPapeletaController::class, 'store'])->name('store');
        Route::get('/{papeleta}', [TrabajadorPapeletaController::class, 'show'])->name('show');
        Route::get('/{papeleta}/pdf', [TrabajadorPdfController::class, 'papeleta'])->name('pdf');
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
        Route::get('/papeletas', JefeIndex::class)->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [JefePapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [JefeDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [JefeDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [JefeDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/reconocer-observacion-rrhh', [JefeDecisionController::class, 'reconocerObservacionRrhh'])->name('papeletas.reconocer-observacion-rrhh');
        Route::post('/papeletas/{papeleta}/responder-posthoc', [JefeDecisionController::class, 'responderPosthoc'])->name('papeletas.responder-posthoc');
        Route::post('/papeletas/{papeleta}/retorno-manual', [JefeDecisionController::class, 'retornoManual'])->name('papeletas.retorno-manual');

    });

    // --- RRHH (Paso 3 y Paso 4) ---
    Route::prefix('rrhh')->name('rrhh.')->middleware('role:rrhh')->group(function () {
        Route::get('/papeletas', RrhhIndex::class)->name('papeletas.index');
        Route::get('/papeletas/{papeleta}', [RrhhPapeletaController::class, 'show'])->name('papeletas.show');

        Route::post('/papeletas/{papeleta}/aprobar', [RrhhDecisionController::class, 'aprobar'])->name('papeletas.aprobar');
        Route::post('/papeletas/{papeleta}/rechazar', [RrhhDecisionController::class, 'rechazar'])->name('papeletas.rechazar');
        Route::post('/papeletas/{papeleta}/observar', [RrhhDecisionController::class, 'observar'])->name('papeletas.observar');
        Route::post('/papeletas/{papeleta}/posthoc-aprobar', [RrhhDecisionController::class, 'posthocAprobar'])->name('papeletas.posthoc-aprobar');
        Route::post('/papeletas/{papeleta}/posthoc-observar', [RrhhDecisionController::class, 'posthocObservar'])->name('papeletas.posthoc-observar');
        Route::post('/papeletas/{papeleta}/posthoc-no-aprobar', [RrhhDecisionController::class, 'posthocNoAprobar'])->name('papeletas.posthoc-no-aprobar');
        Route::post('/papeletas/{papeleta}/corregir', [RrhhDecisionController::class, 'corregir'])->name('papeletas.corregir');

        Route::post('/sustentos/{sustento}/revisar', [RrhhSustentoController::class, 'revisar'])->name('sustentos.revisar');
    });
});
