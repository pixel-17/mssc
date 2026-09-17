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
