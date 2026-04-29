<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error - <?= e((string) config('app.name', 'Kyros Pulse')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', system-ui, sans-serif; background: #0B1020; color: #fff; }</style>
</head>
<body class="min-h-screen flex items-center justify-center px-6">
    <div class="text-center max-w-md">
        <?= \App\Core\View::section('content') ?>
    </div>
</body>
</html>
