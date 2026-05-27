<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#0b1828">
    <title>{{ $title ?? 'Hoja de Ruta' }} · Verdeo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        .ms-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 16px;
        }
        .ms-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
            text-decoration: none;
        }
        .ms-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .ms-btn-green  { background: rgba(78,158,90,0.2);  color: #4ade80; border: 1px solid rgba(78,158,90,0.4); }
        .ms-btn-blue   { background: rgba(59,130,246,0.2); color: #93c5fd; border: 1px solid rgba(59,130,246,0.4); }
        .ms-btn-yellow { background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.35); }
        .ms-btn-gray   { background: rgba(255,255,255,0.06); color: #94a3b8; border: 1px solid rgba(255,255,255,0.1); }
        .ms-btn-red    { background: rgba(239,68,68,0.15);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
