<?php
/**
 * Layout minimal para el wizard de onboarding. Sin sidebar, sin distracciones.
 * Solo progress bar + content + footer con "saltar todo".
 *
 * @var int $progress 0..100
 * @var int $step
 * @var int $totalSteps
 */
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido Â· <?= e((string) config('app.name', 'Evallish Pulse')) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            background: #060B16;
            color: #F8FAFC;
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
        }
        .glow-bg::before {
            content: '';
            position: fixed; inset: 0;
            background:
                radial-gradient(60% 50% at 20% 0%, rgba(139,92,246,.12), transparent 60%),
                radial-gradient(50% 40% at 80% 100%, rgba(6,182,212,.08), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }
        .surface {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
        }
        .step-dot { transition: all .3s; }
        .step-dot.done { background: linear-gradient(135deg,#10B981,#06B6D4); }
        .step-dot.current { background: linear-gradient(135deg,#8B5CF6,#06B6D4); box-shadow: 0 0 0 4px rgba(139,92,246,.15); }
        .step-dot.pending { background: rgba(255,255,255,.08); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="antialiased min-h-screen glow-bg relative">

<div class="relative z-10 min-h-screen flex flex-col">
    <!-- Header con logo + skip -->
    <header class="flex items-center justify-between px-6 sm:px-10 py-5">
        <a href="<?= url('/dashboard') ?>" class="inline-flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-white p-1" style="box-shadow: 0 8px 24px rgba(37,99,235,0.35);">
                <img src="<?= asset('css/logo.png') ?>" alt="Evallish Pulse" class="w-full h-full object-contain">
            </div>
            <span class="font-bold text-white tracking-tight text-lg"><?= e((string) config('app.name', 'Evallish Pulse')) ?></span>
        </a>
        <form action="<?= url('/onboarding/skip-all') ?>" method="POST" class="inline" onsubmit="return confirm('Saltarse el setup? Puedes retomarlo despues desde el banner del dashboard.')">
            <?= csrf_field() ?>
            <button class="text-sm text-slate-400 hover:text-white transition">Saltarse todo â†’</button>
        </form>
    </header>

    <!-- Progress bar -->
    <div class="px-6 sm:px-10 mb-6">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-2 text-[10px] uppercase tracking-wider font-semibold text-slate-400">
                <span>Paso <?= (int) $step + 1 ?> de <?= (int) $totalSteps ?></span>
                <span><?= (int) $progress ?>%</span>
            </div>
            <div class="h-1.5 rounded-full overflow-hidden" style="background: rgba(255,255,255,.06);">
                <div class="h-full rounded-full transition-all duration-500" style="width: <?= (int) $progress ?>%; background: linear-gradient(90deg,#8B5CF6,#06B6D4);"></div>
            </div>
            <!-- Step dots -->
            <div class="flex items-center justify-between mt-3 max-w-md mx-auto">
                <?php
                $stepLabels = ['Bienvenida', 'Negocio', 'WhatsApp', 'Agente IA', 'Workflow'];
                foreach ($stepLabels as $i => $label):
                    $stateClass = $i < (int) $step ? 'done' : ($i === (int) $step ? 'current' : 'pending');
                ?>
                <div class="flex flex-col items-center gap-1.5 flex-1">
                    <span class="step-dot w-2.5 h-2.5 rounded-full <?= $stateClass ?>"></span>
                    <span class="text-[10px] <?= $stateClass === 'pending' ? 'text-slate-500' : 'text-slate-300' ?>"><?= e($label) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Content -->
    <main class="flex-1 px-6 sm:px-10 pb-10">
        <div class="max-w-3xl mx-auto">
            <?php if ($flash = flash('error')): ?>
            <div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FCA5A5;"><?= e((string) $flash) ?></div>
            <?php endif; ?>
            <?php if ($flash = flash('success')): ?>
            <div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#6EE7B7;"><?= e((string) $flash) ?></div>
            <?php endif; ?>

            <?= \App\Core\View::section('content') ?>
        </div>
    </main>

    <footer class="px-6 sm:px-10 py-5 text-center text-[10px] text-slate-600">
        &copy; <?= date('Y') ?> <?= e((string) config('app.name', 'Evallish Pulse')) ?> Â· Setup inicial
    </footer>
</div>

</body>
</html>
