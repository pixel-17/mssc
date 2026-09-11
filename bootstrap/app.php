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

        // Corre contra la ventana activa de turno/día (incluye turnos
        // nocturnos que cruzan medianoche) -> necesita granularidad fina.
        $schedule->command('papeletas:procesar-vencimientos')
            ->everyMinute()
            ->withoutOverlapping();

        // Detecta fin de turno sin marcación de retorno. No es tan
        // sensible al minuto exacto como el de arriba.
        $schedule->command('papeletas:procesar-abandono-no-marcado')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Ventana de 48h hábiles: basta con revisarlo cada hora.
        $schedule->command('papeletas:procesar-vencimiento-sustentos')
            ->hourly()
            ->withoutOverlapping();

        // Ventana de días hábiles (config SUBSANACION_EMERGENCIA_DIAS_HABILES,
        // 15 por defecto): tampoco necesita granularidad de minuto.
        $schedule->command('papeletas:procesar-subsanacion-emergencia-vencida')
            ->hourly()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de spatie/laravel-permission, usado por 'role:admin' en
        // routes/web.php. Sin esto, el middleware de las rutas /admin
        // rompe con "Target class [role] does not exist".
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
