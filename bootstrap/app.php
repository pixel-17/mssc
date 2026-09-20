<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Paso 8 / flujo-papeletas.md: estos 3 jobs son el único mecanismo
        // que hace avanzar el automatismo (VENCIDA, abandono no marcado,
        // sustento de salud vencido). Sin registrarlos aquí, existen como
        // comandos pero nadie los ejecuta y ninguna papeleta vence sola.

        // withoutOverlapping(N): N = minutos que dura el candado si el proceso muere
        // a la mitad (sin N son 1440, es decir, 24 h sin correr). Debe ser mayor que lo
        // que tarda el comando y menor que el intervalo lo bastante para reponerse.

        // Corre contra la ventana activa de turno/día (incluye turnos
        // nocturnos que cruzan medianoche) -> necesita granularidad fina.
        $schedule->command('papeletas:procesar-vencimientos')
            ->everyMinute()
            ->withoutOverlapping(5);

        // Detecta fin de turno sin marcación de retorno. No es tan
        // sensible al minuto exacto como el de arriba.
        $schedule->command('papeletas:procesar-abandono-no-marcado')
            ->everyFiveMinutes()
            ->withoutOverlapping(15);

        // Ventana de 48h hábiles: basta con revisarlo cada hora.
        $schedule->command('papeletas:procesar-vencimiento-sustentos')
            ->hourly()
            ->withoutOverlapping(30);

        // Día 25: si para esa fecha Admin/Jefe no cargaron el turno
        // del mes siguiente, se genera solo continuando el ciclo
        // vigente (ver GeneradorTurnoMensualService). Corre antes de
        // fin de mes para dar margen a que alguien cargue una
        // actualización manual sin que el automático se le adelante
        // en el último día.
        $schedule->command('turnos:generar-proximo-mes')
            ->monthlyOn(25, '02:00')
            ->withoutOverlapping(120);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de spatie/laravel-permission, usado por 'role:admin' en
        // routes/web.php. Sin esto, el middleware de las rutas /admin
        // rompe con "Target class [role] does not exist".
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Manda a /actualizar-password-inicial a quien todavía tiene
        // pendiente cambiar la contraseña = DNI que le asignaron al
        // crearlo (ver CrearUsuarioAction / UsuarioAdminForm). Va al
        // final del grupo 'web' porque necesita que Auth ya esté
        // resuelto; internamente solo actúa si corresponde.
        $middleware->web(append: [
            \App\Http\Middleware\CabecerasDeSeguridad::class,
            \App\Http\Middleware\EnsureUsuarioActivo::class,
            \App\Http\Middleware\RedirigirSiDebeActualizarPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
