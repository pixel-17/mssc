import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Azul acero institucional — menos saturado que el cyan anterior,
                // pensado para leer bien como superficie (sidebar, cards), no solo como fondo vistoso.
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
                // Acento cálido (terracota / adobe) — referencia a la arquitectura de Cusco,
                // usado con moderación para estados activos y llamadas a la acción.
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
            backgroundImage: {
                'ocean-gradient': 'linear-gradient(135deg, #0f1c2e 0%, #1d3654 40%, #2c5480 75%, #3b699e 100%)',
                'ocean-gradient-soft': 'linear-gradient(135deg, #f2f6fb 0%, #e2eaf5 50%, #c2d3e8 100%)',
                'ocean-radial': 'radial-gradient(circle at 15% 10%, rgba(59,105,158,.20), transparent 45%), radial-gradient(circle at 85% 0%, rgba(15,28,46,.35), transparent 50%), radial-gradient(circle at 50% 100%, rgba(212,101,47,.08), transparent 45%)',
            },
            boxShadow: {
                glass: '0 8px 32px 0 rgba(15, 28, 46, 0.14)',
                'glass-lg': '0 20px 60px -10px rgba(15, 28, 46, 0.30)',
                'ocean-glow': '0 0 0 1px rgba(255,255,255,.4) inset, 0 8px 24px -6px rgba(44,84,128,.45)',
                'andino-glow': '0 0 0 1px rgba(255,255,255,.4) inset, 0 8px 24px -6px rgba(185,79,34,.5)',
            },
            backdropBlur: {
                xs: '2px',
            },
            keyframes: {
                'float-slow': {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-14px)' },
                },
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'float-slow': 'float-slow 7s ease-in-out infinite',
                'fade-in-up': 'fade-in-up .4s ease-out',
            },
        },
    },

    plugins: [forms, typography],
};
