<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Arma la navegación del shell (sidebar para quien gestiona a otros,
 * barra inferior para el trabajador). Antes esto vivía en un @php de
 * ~90 líneas dentro de layouts/app.blade.php, con dos consultas COUNT en
 * cada carga de página. Ahora:
 *
 *  - la estructura de menús se arma aquí (y se puede probar);
 *  - los contadores de las bandejas NO se calculan aquí: cada ítem lleva
 *    `bandeja => 'jefe'|'rrhh'` y el sidebar pinta el componente Livewire
 *    Navegacion\InsigniaBandeja, que se actualiza en vivo.
 *
 * Se registra para 'layouts.app' y 'components.trabajador-layout'
 * (AppServiceProvider). Variables que entrega a la vista: usuario, esAdmin,
 * esRrhh, esJefe, puedeAltaUsuarios, conSidebar, rolLabel, secciones, navItems.
 */
class NavegacionComposer
{
    public function compose(View $view): void
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();

        if (! $usuario) {
            $view->with([
                'usuario' => null,
                'esAdmin' => false,
                'esRrhh' => false,
                'esJefe' => false,
                'puedeAltaUsuarios' => false,
                'conSidebar' => false,
                'rolLabel' => '',
                'secciones' => [],
                'navItems' => [],
            ]);

            return;
        }

        $esAdmin = $usuario->hasRole('admin');
        $esRrhh = $usuario->hasRole('rrhh');
        $esJefe = $usuario->can('crearTrabajadorPropio', User::class);
        $puedeAltaUsuarios = $usuario->can('puedeCrearAlgo', User::class);

        $view->with([
            'usuario' => $usuario,
            'esAdmin' => $esAdmin,
            'esRrhh' => $esRrhh,
            'esJefe' => $esJefe,
            'puedeAltaUsuarios' => $puedeAltaUsuarios,
            // El shell con sidebar es para quien gestiona a otros. El
            // trabajador sin gente a cargo usa el shell móvil.
            'conSidebar' => $esAdmin || $esRrhh || $esJefe,
            'rolLabel' => $esAdmin ? 'Administración' : ($esRrhh ? 'Recursos Humanos' : 'Jefatura'),
            'secciones' => $this->secciones($usuario, $esAdmin, $esRrhh, $esJefe, $puedeAltaUsuarios),
            'navItems' => $this->navegacionDelTrabajador(),
        ]);
    }

    /**
     * @return array<int, array{label: ?string, items: array<int, array<string, mixed>>}>
     */
    private function secciones(User $usuario, bool $esAdmin, bool $esRrhh, bool $esJefe, bool $puedeAltaUsuarios): array
    {
        $secciones = [[
            'label' => null,
            'items' => [
                ['label' => 'Inicio', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
            ],
        ]];

        $bandejas = [];

        if ($esJefe) {
            $bandejas[] = ['label' => 'Papeletas de mi equipo', 'route' => route('jefe.papeletas.index'), 'active' => request()->routeIs('jefe.*'), 'icon' => 'inbox', 'bandeja' => 'jefe'];
        }

        if ($esRrhh) {
            $bandejas[] = ['label' => 'Papeletas de RR. HH.', 'route' => route('rrhh.papeletas.index'), 'active' => request()->routeIs('rrhh.*'), 'icon' => 'clipboard', 'bandeja' => 'rrhh'];
        }

        if ($usuario->hasRole('trabajador')) {
            $bandejas[] = ['label' => 'Mis papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.*'), 'icon' => 'document'];
        }

        if ($bandejas) {
            $secciones[] = ['label' => 'Bandejas', 'items' => $bandejas];
        }

        $turnosJefe = [];

        if ($esJefe) {
            $turnosJefe[] = ['label' => 'Calendario del equipo', 'route' => route('turnos.calendario.equipo'), 'active' => request()->routeIs('turnos.calendario.equipo'), 'icon' => 'calendar'];
            $turnosJefe[] = ['label' => 'Programar horarios', 'route' => route('turnos.programacion.equipo'), 'active' => request()->routeIs('turnos.programacion.*'), 'icon' => 'clock'];
        }

        if ($turnosJefe) {
            $secciones[] = ['label' => 'Turnos', 'items' => $turnosJefe];
        }

        // Reportes: mismo criterio de acceso que las rutas (admin, RR. HH. o
        // jefe). Cada componente ya recorta los datos al alcance del usuario.
        $secciones[] = ['label' => 'Reportes', 'items' => [
            ['label' => 'Horas acumuladas', 'route' => route('reportes.horas-acumuladas'), 'active' => request()->routeIs('reportes.horas-acumuladas'), 'icon' => 'chart'],
            ['label' => 'Ficha de trabajador', 'route' => route('reportes.trabajador-historial'), 'active' => request()->routeIs('reportes.trabajador-historial'), 'icon' => 'user-circle'],
            ['label' => 'Sustentos', 'route' => route('reportes.sustentos'), 'active' => request()->routeIs('reportes.sustentos'), 'icon' => 'clipboard'],
        ]];

        $personas = [];

        if ($puedeAltaUsuarios) {
            $personas[] = ['label' => 'Trabajadores', 'route' => route('usuarios.index'), 'active' => request()->routeIs('usuarios.*'), 'icon' => 'users'];
        }

        if ($esAdmin) {
            $personas[] = ['label' => 'Cuentas de usuario', 'route' => route('usuarios-admin.index'), 'active' => request()->routeIs('usuarios-admin.*'), 'icon' => 'user-circle'];
        }

        if ($personas) {
            $secciones[] = ['label' => 'Personas', 'items' => $personas];
        }

        if ($esAdmin) {
            $secciones[] = ['label' => 'Catálogos', 'items' => [
                ['label' => 'Resumen', 'route' => route('catalogos.index'), 'active' => request()->routeIs('catalogos.*'), 'icon' => 'grid'],
                ['label' => 'Sedes', 'route' => route('sedes.index'), 'active' => request()->routeIs('sedes.*'), 'icon' => 'map-pin'],
                ['label' => 'Unidades orgánicas', 'route' => route('unidades-organicas.index'), 'active' => request()->routeIs('unidades-organicas.*'), 'icon' => 'building'],
                ['label' => 'Motivos', 'route' => route('motivos.index'), 'active' => request()->routeIs('motivos.*'), 'icon' => 'tag'],
                ['label' => 'Turnos', 'route' => route('turnos.index'), 'active' => request()->routeIs('turnos.*'), 'icon' => 'clock'],
                ['label' => 'Feriados', 'route' => route('feriados.index'), 'active' => request()->routeIs('feriados.*'), 'icon' => 'calendar'],
            ]];

            $secciones[] = ['label' => 'Sistema', 'items' => [
                ['label' => 'Configuraciones', 'route' => route('configuraciones.index'), 'active' => request()->routeIs('configuraciones.*'), 'icon' => 'cog'],
            ]];
        }

        return $secciones;
    }

    /**
     * Barra inferior del shell móvil (trabajador sin gente a cargo).
     *
     * @return array<int, array<string, mixed>>
     */
    private function navegacionDelTrabajador(): array
    {
        return [
            ['label' => 'Papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.papeletas.index', 'trabajador.papeletas.show'), 'icon' => 'document'],
            ['label' => 'Nueva', 'route' => route('trabajador.papeletas.create'), 'active' => request()->routeIs('trabajador.papeletas.create'), 'icon' => 'plus-circle'],
            ['label' => 'Calendario', 'route' => route('turnos.calendario.individual'), 'active' => request()->routeIs('turnos.calendario.individual'), 'icon' => 'calendar'],
            ['label' => 'Perfil', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.show'), 'icon' => 'user-circle'],
        ];
    }
}