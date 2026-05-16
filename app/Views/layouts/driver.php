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
    </style>
</head>
<body class="antialiased min-h-screen">
    <?= \App\Core\View::section('content') ?>
</body>
</html>
