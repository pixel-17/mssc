import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Rebrand empresarial: "brand" pasa de celeste a la escala
            // "ocean" (azul institucional: sidebar, headers, enlaces) y se
            // agrega "accent" (terracota, único color cálido del sistema)
            // para CTAs primarios, badges de conteo y estados activos.
            // Mismos puntos replicados en resources/css/app.css (botón
            // primario, sidebar, bottom-nav) para mantener todo coherente.
            // Se conserva el nombre "brand" para no romper los ~46 usos
            // existentes en las vistas (bg-brand-600, text-brand-700, etc).
            colors: {
                brand: {
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
                accent: {
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
                // Usada en tarjetas de acceso rápido / avatares con
                // degradado (dashboard admin); tampoco estaba definida.
                glass: '0 4px 14px -4px rgba(14, 165, 233, 0.35)',
                // shadow-glass-lg: usada en dropdowns, toasts, la campana
                // de notificaciones y los íconos "hero" de login/errores/
                // bloqueo — tampoco estaba definida, así que esos paneles
                // flotantes se veían sin elevación (planos).
                'glass-lg': '0 16px 40px -12px rgba(15, 15, 20, 0.28)',
            },
        },
    },

    plugins: [forms],
};
