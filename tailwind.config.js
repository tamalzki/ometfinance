const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/css/**/*.css',
        './resources/js/**/*.js',
        './app/View/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                'omet-red': '#E8271A',
                'omet-blue': '#1565C0',
                'omet-navy': '#0D1B2A',
                'omet-lightblue': '#1976D2',
                'omet-bg': '#F0F4F8',
                'omet-card': '#FFFFFF',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};
