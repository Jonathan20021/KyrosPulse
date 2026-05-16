<?php /** @var array $data */ ?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#060B16">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Driver · <?= e((string) config('app.name', 'Evallish Pulse')) ?></title>
    <link rel="manifest" href="data:application/manifest+json,{}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --color-primary: #10B981;
            --color-bg: #060B16;
            --color-bg-elevated: #0F172A;
            --color-bg-subtle: #1E293B;
            --color-border-subtle: rgba(255,255,255,0.08);
            --color-text-primary: #F8FAFC;
            --color-text-secondary: #CBD5E1;
            --color-text-tertiary: #64748B;
        }
        body { background: var(--color-bg); color: var(--color-text-primary); font-family: 'Inter', sans-serif; padding-bottom: env(safe-area-inset-bottom); }
        .surface { background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle); border-radius: 16px; }
        .tap { transition: transform .1s; }
        .tap:active { transform: scale(.97); }

        /* Pulsing dot for driver location marker (Uber-style) */
        .pulse-dot { width: 18px; height: 18px; border-radius: 50%; background: #10B981; box-shadow: 0 0 0 4px rgba(16,185,129,.35), 0 2px 8px rgba(0,0,0,.5); position: relative; }
        .pulse-dot::after { content:''; position:absolute; inset:-6px; border-radius:50%; background: rgba(16,185,129,.45); animation: pulse 1.8s ease-out infinite; }
        @keyframes pulse { 0% { transform: scale(.5); opacity: 1; } 100% { transform: scale(2.2); opacity: 0; } }

        /* Leaflet dark theme overrides */
        .leaflet-container { background: #0F172A !important; outline: none; }
        .leaflet-control-attribution { background: rgba(15,23,42,.7) !important; color: #64748B !important; font-size: 9px !important; }
        .leaflet-control-attribution a { color: #94A3B8 !important; }
        .leaflet-tile-pane { filter: brightness(.95) contrast(1.05); }
        .leaflet-popup-content-wrapper { background: #1E293B; color: #F8FAFC; border-radius: 12px; }
        .leaflet-popup-tip { background: #1E293B; }
        .leaflet-bar a { background: #1E293B !important; color: #F8FAFC !important; border-color: rgba(255,255,255,.1) !important; }
        .leaflet-bar a:hover { background: #334155 !important; }
    </style>
</head>
<body class="antialiased min-h-screen">
    <?= \App\Core\View::section('content') ?>
</body>
</html>
