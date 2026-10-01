import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx,ts,tsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            animation: { 'fade-up': 'fade-up .45s ease-out both', 'soft-pulse': 'soft-pulse 2.5s ease-in-out infinite' },
            keyframes: {
                'fade-up': { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                'soft-pulse': { '0%, 100%': { opacity: '.55' }, '50%': { opacity: '1' } },
            },
        },
    },

    plugins: [forms],
};
