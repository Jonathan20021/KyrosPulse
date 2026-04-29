<?php /** @var array $data */ ?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · <?= e((string) config('app.name', 'Kyros Pulse')) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { background: #050817; color: #F8FAFC; }
        .auth-bg::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
        }
    </style>
</head>
<body class="antialiased">

<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Left: form -->
    <div class="flex flex-col px-6 sm:px-12 py-8 lg:py-12">
        <header class="flex items-center justify-between mb-12">
            <a href="<?= url('/') ?>" class="inline-flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/30" style="background: var(--gradient-primary);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="font-bold text-white">Kyros<span class="gradient-text">Pulse</span></span>
            </a>
            <a href="<?= url('/') ?>" class="text-sm text-slate-400 hover:text-white transition">← Volver</a>
        </header>

        <div class="flex-1 flex items-center justify-center">
            <div class="w-full max-w-md">
                <?= \App\Core\View::section('content') ?>
            </div>
        </div>

        <footer class="mt-12 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
            <span>&copy; <?= date('Y') ?> Kyros Pulse</span>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-slate-300 transition">Privacidad</a>
                <a href="#" class="hover:text-slate-300 transition">Terminos</a>
                <span class="flex items-center gap-1.5"><span class="live-dot"></span>Sistema operativo</span>
            </div>
        </footer>
    </div>

    <!-- Right: visual -->
    <div class="hidden lg:flex relative overflow-hidden auth-bg" style="background: linear-gradient(135deg, #0F1530 0%, #050817 100%);">
        <div class="absolute inset-0 bg-grid bg-grid-fade opacity-40"></div>
        <div class="absolute -top-32 -left-20 w-[600px] h-[600px] rounded-full opacity-30" style="background: radial-gradient(circle, #7C3AED, transparent 70%); filter: blur(80px);"></div>
        <div class="absolute -bottom-32 -right-20 w-[600px] h-[600px] rounded-full opacity-25" style="background: radial-gradient(circle, #06B6D4, transparent 70%); filter: blur(80px);"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col justify-center px-12 py-20">
            <!-- Pill -->
            <div class="inline-flex items-center gap-2 self-start px-3 py-1.5 mb-8 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
                <span class="live-dot"></span>
                <span class="text-xs font-medium text-slate-300">Powered by Claude Sonnet 6</span>
            </div>

            <h2 class="heading-lg text-white mb-6 leading-tight">
                Convierte cada conversacion en una <span class="gradient-text-aurora">oportunidad</span>
            </h2>
            <p class="text-lg text-slate-400 max-w-md mb-12">
                CRM, WhatsApp multiagente, automatizaciones e IA en una sola plataforma premium.
            </p>

            <!-- Floating dashboard card -->
            <div class="relative max-w-md">
                <div class="absolute -inset-4 bg-gradient-to-r from-violet-500 to-cyan-500 rounded-3xl blur-3xl opacity-30"></div>
                <div class="browser-chrome relative">
                    <div class="browser-chrome-bar">
                        <span class="browser-dot bg-rose-500/70"></span>
                        <span class="browser-dot bg-amber-500/70"></span>
                        <span class="browser-dot bg-emerald-500/70"></span>
                    </div>
                    <div class="bg-[#0A0F25] p-4 space-y-3">
                        <!-- KPI cards -->
                        <div class="grid grid-cols-3 gap-2">
                            <?php foreach ([['Mensajes', '1.2k', '#7C3AED'], ['Leads', '83', '#06B6D4'], ['Ventas', '$18k', '#10B981']] as $kpi): ?>
                            <div class="bg-white/5 rounded-lg p-2.5 border border-white/5">
                                <div class="text-[9px] text-slate-500 uppercase tracking-wider"><?= $kpi[0] ?></div>
                                <div class="font-bold text-white text-sm mt-0.5"><?= $kpi[1] ?></div>
                                <div class="h-1 mt-1.5 rounded-full overflow-hidden bg-white/5"><div class="h-full" style="width: 72%; background: <?= $kpi[2] ?>"></div></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Chart preview -->
                        <div class="bg-white/5 rounded-lg p-3 border border-white/5">
                            <svg viewBox="0 0 280 60" class="w-full h-14">
                                <defs>
                                    <linearGradient id="ag1" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.5"/>
                                        <stop offset="100%" stop-color="#7C3AED" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path d="M0,45 L20,38 L40,42 L60,30 L80,35 L100,22 L120,28 L140,18 L160,22 L180,12 L200,16 L220,8 L240,12 L260,4 L280,8 L280,60 L0,60 Z" fill="url(#ag1)"/>
                                <path d="M0,45 L20,38 L40,42 L60,30 L80,35 L100,22 L120,28 L140,18 L160,22 L180,12 L200,16 L220,8 L240,12 L260,4 L280,8" fill="none" stroke="#7C3AED" stroke-width="2"/>
                            </svg>
                        </div>
                        <!-- Conversation -->
                        <div class="bg-white/5 rounded-lg p-3 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold" style="background: var(--gradient-primary);">M</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-white">Maria Lopez</div>
                                    <div class="text-[10px] text-slate-400">+18091234567 · ahora</div>
                                </div>
                                <span class="badge badge-emerald badge-dot text-[8px]">Live</span>
                            </div>
                            <div class="px-2 py-1.5 rounded-md bg-white/5 text-xs text-slate-300 mb-1">"Estoy interesada en el plan Professional"</div>
                            <div class="px-2 py-1.5 rounded-md text-white text-xs ml-6" style="background: var(--gradient-primary);">"Te envio la cotizacion ahora 🚀"</div>
                        </div>
                        <!-- AI suggestion -->
                        <div class="rounded-lg p-2.5 border border-violet-500/30" style="background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15));">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🤖</span>
                                <div>
                                    <div class="text-[10px] font-semibold text-cyan-300">IA Sugiere</div>
                                    <div class="text-[10px] text-slate-300">"Cliente con alta intencion. Crear tarea de seguimiento."</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="flex items-center gap-8 mt-16">
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-1">10M+</div>
                    <div class="text-xs text-slate-500">Mensajes procesados</div>
                </div>
                <div class="w-px h-12 bg-white/10"></div>
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-1">98%</div>
                    <div class="text-xs text-slate-500">Tasa de respuesta</div>
                </div>
                <div class="w-px h-12 bg-white/10"></div>
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-1">3.5x</div>
                    <div class="text-xs text-slate-500">Mas conversion</div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
