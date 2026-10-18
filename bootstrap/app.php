<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\EnsureUsuarioActivo;
use App\Http\Middleware\RedirigirSiDebeActualizarPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

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

        // Si uno de estos jobs truena, el log es lo único que se entera por
        // defecto — nadie mira logs a las 3am. Esto deja constancia en nivel
        // 'critical' (visible para cualquier canal de alerta que se agregue
        // a LOG_STACK, ej. 'slack', sin tocar este archivo de nuevo) y no
        // depende de la base de datos (si el job falló por la BD caída,
        // notificar por Notification/BD fallaría también).
        $avisarFalla = fn (string $comando) => function () use ($comando): void {
            Log::critical("Scheduler: el comando '{$comando}' falló.", [
                'comando' => $comando,
                'hora' => now()->toDateTimeString(),
            ]);
        };

        // Corre contra la ventana activa de turno/día (incluye turnos
        // nocturnos que cruzan medianoche) -> necesita granularidad fina.
        $schedule->command('papeletas:procesar-vencimientos')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onFailure($avisarFalla('papeletas:procesar-vencimientos'));

        // Detecta fin de turno sin marcación de retorno. No es tan
        // sensible al minuto exacto como el de arriba.
        $schedule->command('papeletas:procesar-abandono-no-marcado')
            ->everyFiveMinutes()
            ->withoutOverlapping(15)
            ->onFailure($avisarFalla('papeletas:procesar-abandono-no-marcado'));

        // Ventana de 48h hábiles: basta con revisarlo cada hora.
        $schedule->command('papeletas:procesar-vencimiento-sustentos')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('papeletas:procesar-vencimiento-sustentos'));

        // Cola de revisión post-hoc de RRHH: avisa si se acumula o envejece.
        // Una vez por hora basta; el servicio ya limita a un aviso por día.
        $schedule->command('papeletas:avisar-posthoc-acumulado')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('papeletas:avisar-posthoc-acumulado'));

        // Día 25: si para esa fecha Admin/Jefe no cargaron el turno
        // del mes siguiente, se genera solo continuando el ciclo
        // vigente (ver GeneradorTurnoMensualService). Corre antes de
        // fin de mes para dar margen a que alguien cargue una
        // actualización manual sin que el automático se le adelante
        // en el último día.
        $schedule->command('turnos:generar-proximo-mes')
            ->monthlyOn(25, '02:00')
            ->withoutOverlapping(120)
            ->onFailure($avisarFalla('turnos:generar-proximo-mes'));

        // Unidades con gente cuya jefatura quedó vacante (baja de un jefe
        // sin reemplazo). No es sensible al minuto: cada hora alcanza.
        $schedule->command('jefaturas:avisar-faltantes')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('jefaturas:avisar-faltantes'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxy/balanceador confiable: sin esto, si el servidor está detrás
        // de algo que termina HTTPS (Nginx, Cloudflare, ALB, etc.), Laravel
        // ve la conexión interna como HTTP plano y $request->isSecure()
        // siempre da false. Eso rompe en silencio dos cosas: la cabecera
        // HSTS de CabecerasDeSeguridad (nunca se envía) y los enlaces que
        // genera url()/asset() (salen en http:// en vez de https://).
        //
        // TRUSTED_PROXIES en .env:
        //   - IP o rango del proxy, separado por comas (ej. "10.0.0.5,10.0.0.6")
        //   - '*' SOLO si el proxy está en una red privada que vos controlás
        //     por completo. NUNCA '*' si la app recibe tráfico directo de
        //     internet sin proxy delante: cualquiera podría falsificar la
        //     cabecera X-Forwarded-Proto y aparentar HTTPS sin estarlo.
        //   - vacío (default): no confía en ningún proxy, como antes.
        $proxiesConfiables = trim((string) env('TRUSTED_PROXIES', ''));

        if ($proxiesConfiables !== '') {
            $middleware->trustProxies(
                at: $proxiesConfiables === '*' ? '*' : array_map('trim', explode(',', $proxiesConfiables)),
            );
        }

        // Alias de spatie/laravel-permission, usado por 'role:admin' en
        // routes/web.php. Sin esto, el middleware de las rutas /admin
        // rompe con "Target class [role] does not exist".
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Manda a /actualizar-password-inicial a quien todavía tiene
        // pendiente cambiar la contraseña = DNI que le asignaron al
        // crearlo (ver CrearUsuarioAction / UsuarioAdminForm). Va al
        // final del grupo 'web' porque necesita que Auth ya esté
        // resuelto; internamente solo actúa si corresponde.
        $middleware->web(append: [
            CabecerasDeSeguridad::class,
            EnsureUsuarioActivo::class,
            RedirigirSiDebeActualizarPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
