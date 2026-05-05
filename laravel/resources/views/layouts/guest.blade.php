<!DOCTYPE html>
<html lang="es" class="h-full bg-verdeo-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Verdeo') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data>
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center mb-8">
                <span class="text-5xl">🌿</span>
                <h1 class="mt-4 text-3xl font-bold text-verdeo-900">Verdeo</h1>
                <p class="text-verdeo-600 text-sm mt-1">Panel de gestión</p>
            </div>
            <div class="card">
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
