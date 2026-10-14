<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Documento' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sub { color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #f3f4f6; font-size: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top; }
        .dentro { color: #047857; font-weight: bold; }
        .fuera { color: #b91c1c; font-weight: bold; }
        .nd { color: #6b7280; }
        .validacion { margin-top: 18px; border: 1px solid #9ca3af; padding: 8px 10px; }
        .validacion .t { font-weight: bold; margin-bottom: 4px; }
        .pie { margin-top: 16px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    @yield('contenido')
    <p class="pie">Generado el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
