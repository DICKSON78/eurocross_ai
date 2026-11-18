<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#7C0E44">
        <meta name="description" content="EuroCross dashboard for translation usage, billing, VAT compliance, API keys, and chatbot management.">

        <title>EuroCross — Dashboard</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-ec-canvas font-sans text-ec-ink antialiased">
        <div id="app"></div>
        <noscript>
            <p style="padding: 2rem; font-family: sans-serif;">
                JavaScript is required to display the EuroCross dashboard.
            </p>
        </noscript>
    </body>
</html>
