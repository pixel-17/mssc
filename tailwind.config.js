import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            // Paleta institucional. `ocean` es el azul de la municipalidad
            // (sidebar, encabezados, enlaces); `terracota` es el único
            // color cálido del sistema y se reserva para la acción
            // principal, el estado activo y los contadores pendientes.
            colors: {
                ocean: {
                    50: '#f2f6fb',
                    100: '#e2eaf5',
                    200: '#c2d3e8',
                    300: '#93b0d3',
                    400: '#5c86b8',
                    500: '#3b699e',
                    600: '#2c5480',
                    700: '#234368',
                    800: '#1d3654',
                    900: '#182c46',
                    950: '#0f1c2e',
                },
                terracota: {
                    50: '#fdf4ef',
                    100: '#fbe6da',
                    200: '#f6cbb0',
                    300: '#eea87d',
                    400: '#e3824f',
                    500: '#d4652f',
                    600: '#b94f22',
                    700: '#97401d',
                    800: '#7a341c',
                    900: '#642c1a',
                    950: '#37150b',
                },
            },

            boxShadow: {
                // Tarjetas de acceso rápido y avatares con degradado.
                glass: '0 4px 14px -4px rgba(44, 84, 128, 0.35)',
                // Dropdowns, toasts, campana de notificaciones e íconos
                // "hero" de login/errores.
                'glass-lg': '0 16px 40px -12px rgba(15, 15, 20, 0.28)',
                // Halo azul: encabezados y tarjetas destacadas.
                'ocean-glow': '0 12px 32px -10px rgba(35, 67, 104, 0.55)',
                // Halo terracota: el botón flotante de "nueva papeleta".
                'andino-glow': '0 10px 24px -6px rgba(212, 101, 47, 0.55)',
            },
        },
    },

    plugins: [forms, typography],
};
