import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans:      ['Barlow', ...defaultTheme.fontFamily.sans],
                condensed: ['Barlow Condensed', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                verdeo: {
                    50:  '#f0f4f0',   // off-white text
                    100: '#d0e8d4',   // light green (badge bg)
                    200: '#b8cec0',   // muted light (sidebar username)
                    300: '#8aafac',   // muted text alt
                    400: '#7d9e84',   // sidebar group labels
                    500: '#4e9e5a',   // brand green light (focus, icons)
                    600: '#3a7d44',   // brand green (buttons, avatar)
                    700: '#16294a',   // navy lighter
                    800: '#0f2035',   // navy mid (nav active bg, borders)
                    900: '#0b1828',   // navy (sidebar, body)
                    950: '#060f18',   // deepest navy
                },
            },
            backdropBlur: {
                xs: '2px',
            },
        },
    },
    plugins: [forms],
};
