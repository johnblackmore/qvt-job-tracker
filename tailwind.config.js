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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
            },
            colors: {
                ink: '#0F172A',
                slate: '#64748B',
                copper: {
                    DEFAULT: '#B45309',
                    light: '#D97706',
                    dark: '#92400E',
                },
                teal: {
                    DEFAULT: '#0F766E',
                    light: '#14B8A6',
                    dark: '#115E59',
                },
                offwhite: '#FAFBFC',
                panel: '#E2E8F0',
            },
            backgroundImage: {
                'gradient-hero': 'linear-gradient(135deg, #FAFBFC 0%, #F1F5F9 100%)',
                'gradient-copper': 'linear-gradient(to right, #B45309, #D97706)',
            },
            boxShadow: {
                card: '0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.06)',
                'card-hover': '0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04)',
                soft: '0 4px 20px -4px rgba(15, 23, 42, 0.08)',
            },
        },
    },

    plugins: [forms],
};
