# MDS - Sistema de Papeletas

Aplicación web para la gestión de **papeletas de salida/permisos del personal**, construida con Laravel 13, Livewire 3 y Jetstream. Permite a los trabajadores solicitar papeletas, a sus jefes aprobarlas u observarlas, y al área de RRHH realizar el seguimiento, control de retornos (con geolocalización) y generación de reportes.

## Características principales

- **Gestión de papeletas**: creación, aprobación, rechazo, observación y subsanación de papeletas de permiso/salida.
- **Flujo por roles**: `admin`, `rrhh`, `trabajador` y `jefe` (definido según el organigrama), cada uno con su propio panel y permisos.
- **Control de retorno con GPS**: registro del retorno del trabajador con su ubicación geográfica.
- **Sustentos**: adjuntar y revisar documentos de sustento de cada papeleta.
- **Organigrama**: administración de unidades orgánicas y movimientos dentro del organigrama.
- **Turnos**: definición de turnos, configuración y programación mensual (individual y por equipo), con calendario de equipo e individual.
- **Sedes y catálogos**: administración de sedes, motivos y catálogos generales del sistema.
- **Reportes**: resumen de papeletas, historial por trabajador, sustentos y decisiones de RRHH; reporte de abandonos.
- **Notificaciones push** (Web Push) y notificaciones en la campanita de la app.
- **Generación de PDF** de las papeletas.
- **Exportaciones** a Excel de reportes.
- **Historial de auditoría** de cambios sobre las papeletas (activity log).

## Stack tecnológico

- **Backend**: PHP 8.4, Laravel 13
- **Frontend reactivo**: Livewire 3, Laravel Jetstream
- **Broadcasting**: Laravel Reverb / Pusher, Laravel Echo
- **Colas y caché**: Redis / Predis
- **Base de datos**: MySQL
- **Autorización**: Spatie Laravel Permission, Spatie Laravel Model States, Spatie Laravel Activitylog, Spatie Laravel Medialibrary
- **PDF y Excel**: barryvdh/laravel-dompdf, maatwebsite/excel
- **Mapas**: Leaflet (para la visualización de la ubicación de retorno)
- **Estilos**: Tailwind CSS
- **Build**: Vite
- **Testing**: PHPUnit, Larastan (PHPStan), Laravel Pint

## Requisitos

- PHP >= 8.4 (con extensión `redis`)
- Composer
- Node.js y npm
- MySQL
- Redis

## Instalación

```bash
git clone https://github.com/pixel-17/mssc.git
cd mssc
composer run setup
npm run dev
```

El comando `composer run setup` se encarga de:

1. Instalar las dependencias de PHP.
2. Copiar `.env.example` a `.env`.
3. Generar la `APP_KEY`.
4. Ejecutar las migraciones.
5. Instalar las dependencias de npm y compilar los assets.

Luego, configura en tu archivo `.env` los datos de conexión a la base de datos, Redis y, si corresponde, las credenciales de Reverb/Pusher y Web Push.

Para poblar datos iniciales (roles, motivos, sedes, unidades orgánicas, configuración, etc.):

```bash
php artisan db:seed
```

## Desarrollo

Para levantar el entorno de desarrollo (servidor, cola, logs y Vite en paralelo):

```bash
composer run dev
```

## Pruebas

```bash
composer run test
```

También se incluyen pruebas end-to-end en Python dentro de la carpeta `e2e/` (flujo de papeleta, retorno con GPS y recorrido por rol).

## Análisis estático y estilo de código

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pint
```

## Estructura del proyecto

```
app/
├── Actions/          Acciones de negocio reutilizables
├── Http/Controllers/ Controladores por rol (Admin, Jefe, Rrhh, Trabajador, Usuario)
├── Livewire/         Componentes Livewire (Papeletas, Turnos, Organigrama, Reportes, etc.)
├── Models/           Modelos de dominio (Papeleta, Retorno, Turno, Sede, UnidadOrganica, etc.)
├── Services/         Lógica de dominio y servicios de aplicación
├── States/           Estados de la papeleta (Spatie Model States)
└── Policies/         Autorización por modelo

database/
├── migrations/       Migraciones de base de datos
└── seeders/          Seeders (roles, motivos, sedes, unidades orgánicas, configuración)

e2e/                  Pruebas end-to-end en Python
resources/views/      Vistas Blade
routes/web.php        Rutas de la aplicación, agrupadas por rol
```

## Licencia

dev_17
