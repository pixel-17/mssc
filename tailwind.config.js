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
                ocean: {
                    50: '#eefbff',
                    100: '#d9f4ff',
                    200: '#b8eaff',
                    300: '#84dcff',
                    400: '#48c6ff',
                    500: '#1ea7ff',
                    600: '#0a86f0',
                    700: '#086ad0',
                    800: '#0c579f',
                    900: '#0f4a80',
                    950: '#0a2c4d',
                },
            },
            backgroundImage: {
                'ocean-gradient': 'linear-gradient(135deg, #0a2c4d 0%, #0c579f 35%, #0a86f0 70%, #1ea7ff 100%)',
                'ocean-gradient-soft': 'linear-gradient(135deg, #eefbff 0%, #d9f4ff 50%, #b8eaff 100%)',
                'ocean-radial': 'radial-gradient(circle at 20% 20%, rgba(30,167,255,.35), transparent 45%), radial-gradient(circle at 80% 0%, rgba(10,44,77,.5), transparent 50%), radial-gradient(circle at 50% 100%, rgba(72,198,255,.25), transparent 45%)',
            },
            boxShadow: {
                glass: '0 8px 32px 0 rgba(10, 44, 77, 0.18)',
                'glass-lg': '0 20px 60px -10px rgba(10, 44, 77, 0.35)',
                'ocean-glow': '0 0 0 1px rgba(255,255,255,.4) inset, 0 8px 24px -6px rgba(10,134,240,.45)',
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
