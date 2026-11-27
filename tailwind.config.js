import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Thème sombre piloté par la classe "dark" sur <html> (voir resources/js/lib/theme.js).
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Polices embarquées localement (@fontsource) : aucune dépendance Internet.
                sans: ['Inter', 'Noto Sans Arabic', ...defaultTheme.fontFamily.sans],
                arabic: ['Noto Sans Arabic', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: colors.teal,
            },
        },
    },

    plugins: [forms],
};
