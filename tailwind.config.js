/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.{js,vue,jsx,ts,php}',
        './storage/framework/views/*.php',
        './resources/views/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                poppins: ['Poppins', 'sans-serif'],
            },
            colors: {
                primary: '#7C0E44',
                'primary-dark': '#6B0C3E',
                'primary-light': '#9A1255',
                'primary-lighter': '#BC1A6E',
                accent: '#E82757',
                background: '#F5F0F7',
                surface: '#FFFFFF',
                text: '#2D1B20',
                'text-light': '#5A3A42',
                border: '#D1A8C8',
            },
        },
    },
    plugins: [],
};