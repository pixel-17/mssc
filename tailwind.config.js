import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */

// ------------------------------------------------------------------
// Identidad "Registro" (rediseño). El sistema reemplaza un papel
// físico —la papeleta de permiso, con folio y sello— así que la
// paleta nace de ahí: papel frío + una sola tinta de sello cálida.
//
// `tinta` y `sello` son los nombres nuevos. `ocean` y `terracota`
// quedan como ALIAS de los mismos valores (no como paleta aparte):
// así, sin tocar las ~130 vistas que ya usan `text-ocean-950` o
// `bg-terracota-500`, todo el sistema hereda la nueva identidad de
// una sola vez. Las vistas nuevas o reescritas usan `tinta-*`/`sello-*`
// directamente; el resto migra de a poco, sin urgencia ni riesgo.
// ------------------------------------------------------------------
const tinta = {
    50: '#f3f5f4',
    100: '#e2e8e7',
    200: '#c3d0ce',
    300: '#98adab',
    400: '#647d7d',
    500: '#436164',
    600: '#324e52',
    700: '#293f43',
    800: '#223437',
    900: '#1b2b34',
    950: '#101c22',
};

const sello = {
    50: '#faf5ea',
    100: '#f2e5c6',
    200: '#e5c98a',
    300: '#d6ab55',
    400: '#c6902f',
    500: '#b8792f',
    600: '#9a6027',
    700: '#7c4c24',
    800: '#653e22',
    900: '#54331f',
    950: '#301b10',
};

// ------------------------------------------------------------------
// Colores de acento. Se suman a tinta/sello (no los reemplazan) para
// dar más color a badges de estado, iconos, avatares y gráficos de
// reportes en los dashboards. Úsalos como acento puntual, no como
// fondo de página ni como reemplazo de tinta/sello/registro/alarma.
// ------------------------------------------------------------------
const azul = {
    50: '#eff6ff',
    100: '#dbeafe',
    200: '#bfdbfe',
    300: '#93c5fd',
    400: '#60a5fa',
    500: '#3b82f6',
    600: '#2563eb',
    700: '#1d4ed8',
    800: '#1e40af',
    900: '#1e3a8a',
    950: '#172554',
};

const verde = {
    50: '#ecfdf5',
    100: '#d1fae5',
    200: '#a7f3d0',
    300: '#6ee7b7',
    400: '#34d399',
    500: '#10b981',
    600: '#059669',
    700: '#047857',
    800: '#065f46',
    900: '#064e3b',
    950: '#022c22',
};

const morado = {
    50: '#faf5ff',
    100: '#f3e8ff',
    200: '#e9d5ff',
    300: '#d8b4fe',
    400: '#c084fc',
    500: '#a855f7',
    600: '#9333ea',
    700: '#7e22ce',
    800: '#6b21a8',
    900: '#581c87',
    950: '#3b0764',
};

const ambar = {
    50: '#fffbeb',
    100: '#fef3c7',
    200: '#fde68a',
    300: '#fcd34d',
    400: '#fbbf24',
    500: '#f59e0b',
    600: '#d97706',
    700: '#b45309',
    800: '#92400e',
    900: '#78350f',
    950: '#451a03',
};

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/resources/views/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/Support/**/*.php',
    ],

    // resources/js/theme.js pone/quita la clase `dark` en <html> a mano.
    // Sin esta línea Tailwind usaría prefers-color-scheme y las ~290
    // utilidades dark: de las vistas ignorarían el botón de tema.
    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                // Inter: toda la interfaz funcional (formularios, botones,
                // navegación, texto de cuerpo). Legible a tamaños chicos en
                // pantallas de gama baja, que es como muchos trabajadores
                // entran al sistema desde la calle.
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Fraunces: la identidad (folios, títulos, cifras grandes
                // del dashboard, wordmark). Con carácter propio de
                // documento/registro oficial, no de plantilla genérica.
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
                // Para el número de folio (dato tabular tipo "N.° 0042"),
                // no como recurso decorativo repartido por la interfaz.
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                tinta,
                sello,
                // Alias históricos — mismos valores, para no romper el
                // resto del sistema mientras se migra vista por vista.
                ocean: tinta,
                terracota: sello,
                // Estados del registro: aprobar (verde-registro) y
                // rechazar/vencer (rojo-sello). Tonos apagados de tinta
                // de sello, no semáforo saturado.
                registro: {
                    50: '#f1f6f2',
                    100: '#dcebe0',
                    500: '#3f6b4f',
                    600: '#335840',
                    700: '#2a4735',
                },
                alarma: {
                    50: '#faf0ee',
                    100: '#f2d9d5',
                    500: '#a23b33',
                    600: '#872f29',
                    700: '#6c2621',
                },
                // Acentos nuevos: azul, verde, morado, ámbar. Disponibles
                // como bg-azul-500, text-verde-600, border-morado-400, etc.
                azul,
                verde,
                morado,
                ambar,
            },

            boxShadow: {
                glass: '0 4px 14px -4px rgba(27, 43, 52, 0.35)',
                'glass-lg': '0 16px 40px -12px rgba(16, 20, 22, 0.28)',
                'ocean-glow': '0 12px 32px -10px rgba(41, 63, 67, 0.55)',
                'andino-glow': '0 10px 24px -6px rgba(184, 121, 47, 0.55)',
            },
        },
    },

    plugins: [forms, typography],
};
