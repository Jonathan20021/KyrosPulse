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
        .auth-noise::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        .float-1 { animation: floatUp 8s ease-in-out infinite; }
        .float-2 { animation: floatUp 9s ease-in-out infinite; animation-delay: -3s; }
        .float-3 { animation: floatUp 10s ease-in-out infinite; animation-delay: -6s; }
        @keyframes floatUp { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .channel-pill { backdrop-filter: blur(10px); }
    </style>
</head>
<body class="antialiased min-h-screen">

<div class="min-h-screen grid lg:grid-cols-2 xl:grid-cols-[minmax(0,560px)_1fr]">
    <!-- Left: form -->
    <div class="flex flex-col px-6 sm:px-10 lg:px-12 py-8 lg:py-10 relative">
        <header class="flex items-center justify-between mb-10">
            <a href="<?= url('/') ?>" class="inline-flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/30 group-hover:scale-110 transition" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="font-bold text-white tracking-tight"><?= e((string) config('app.name', 'Kyros Pulse')) ?></span>
            </a>
            <a href="<?= url('/') ?>" class="text-sm text-slate-400 hover:text-white transition flex items-center gap-1 group">
                <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </header>

        <div class="flex-1 flex items-center justify-center">
            <div class="w-full max-w-md">
                <?= \App\Core\View::section('content') ?>
            </div>
        </div>

        <footer class="mt-10 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
            <span>&copy; <?= date('Y') ?> <?= e((string) config('app.name', 'Kyros Pulse')) ?></span>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-slate-300 transition">Privacidad</a>
                <a href="#" class="hover:text-slate-300 transition">Terminos</a>
                <span class="flex items-center gap-1.5"><span class="live-dot"></span>Sistemas operativos</span>
            </div>
        </footer>
    </div>

    <!-- Right: visual showcase -->
    <div class="hidden lg:flex relative overflow-hidden auth-noise" style="background: radial-gradient(ellipse at top, #1A1240 0%, #050817 70%);">
        <div class="absolute inset-0 bg-grid bg-grid-fade opacity-40"></div>
        <div class="absolute -top-40 -left-40 w-[700px] h-[700px] rounded-full opacity-30" style="background: radial-gradient(circle, #7C3AED, transparent 60%); filter: blur(80px);"></div>
        <div class="absolute -bottom-40 -right-40 w-[700px] h-[700px] rounded-full opacity-25" style="background: radial-gradient(circle, #06B6D4, transparent 60%); filter: blur(80px);"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] rounded-full opacity-15" style="background: radial-gradient(circle, #EC4899, transparent 60%); filter: blur(100px);"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col justify-center px-10 xl:px-16 py-16 w-full">
            <!-- Pill -->
            <div class="inline-flex items-center gap-2 self-start px-3 py-1.5 mb-6 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
                <span class="live-dot"></span>
                <span class="text-xs font-medium text-slate-300">CRM · Multi-Channel · IA</span>
            </div>

            <h2 class="text-4xl xl:text-5xl font-black text-white mb-5 leading-[1.1] tracking-tight">
                Atiende, vende y crece desde <br>
                <span class="gradient-text-aurora">una sola bandeja</span>
            </h2>
            <p class="text-base xl:text-lg text-slate-400 max-w-md mb-10 leading-relaxed">
                Multi-numero WhatsApp + Cloud API + agentes IA + 30 integraciones. Plataforma todo-en-uno para contact centers serios.
            </p>

            <!-- Floating mockup: omni-channel inbox -->
            <div class="relative max-w-md">
                <div class="absolute -inset-6 bg-gradient-to-tr from-violet-500 via-fuchsia-500 to-cyan-500 rounded-[2rem] blur-3xl opacity-30"></div>
                <div class="browser-chrome relative shadow-2xl">
                    <div class="browser-chrome-bar">
                        <span class="browser-dot bg-rose-500/70"></span>
                        <span class="browser-dot bg-amber-500/70"></span>
                        <span class="browser-dot bg-emerald-500/70"></span>
                        <div class="ml-3 px-2 py-0.5 rounded-md bg-white/5 text-[10px] text-slate-500 font-mono">app.kyrospulse.com/inbox</div>
                    </div>
                    <div class="bg-[#0A0F25] p-4 space-y-3">
                        <!-- Channels switcher -->
                        <div class="flex gap-1.5 flex-wrap">
                            <span class="channel-pill px-2 py-0.5 rounded-full text-[9px] font-bold border" style="background: rgba(16,185,129,.15); color:#10B981; border-color: rgba(16,185,129,.4);">● DO · +1809</span>
                            <span class="channel-pill px-2 py-0.5 rounded-full text-[9px] text-slate-400" style="background: rgba(255,255,255,.04);">MX · +52</span>
                            <span class="channel-pill px-2 py-0.5 rounded-full text-[9px] text-slate-400" style="background: rgba(255,255,255,.04);">VIP US · +1305</span>
                            <span class="channel-pill px-2 py-0.5 rounded-full text-[9px] text-slate-400" style="background: rgba(255,255,255,.04);">+ Cloud API</span>
                        </div>

                        <!-- KPIs -->
                        <div class="grid grid-cols-3 gap-2">
                            <?php foreach ([['Mensajes', '1.2k', '#7C3AED'], ['Leads', '83', '#06B6D4'], ['Cierres IA', '47', '#10B981']] as $kpi): ?>
                            <div class="bg-white/5 rounded-lg p-2.5 border border-white/5">
                                <div class="text-[9px] text-slate-500 uppercase tracking-wider"><?= $kpi[0] ?></div>
                                <div class="font-bold text-white text-sm mt-0.5"><?= $kpi[1] ?></div>
                                <div class="h-1 mt-1.5 rounded-full overflow-hidden bg-white/5"><div class="h-full" style="width: 72%; background: <?= $kpi[2] ?>"></div></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Conversation -->
                        <div class="bg-white/5 rounded-lg p-3 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold" style="background: var(--gradient-primary);">M</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-white">Maria Lopez</div>
                                    <div class="text-[9px] text-emerald-400 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Ventas DO · WhatsApp Cloud
                                    </div>
                                </div>
                                <span class="badge badge-emerald badge-dot text-[8px]">Live</span>
                            </div>
                            <div class="px-2 py-1.5 rounded-md bg-white/5 text-xs text-slate-300 mb-1">"Estoy interesada en el plan Pro"</div>
                            <div class="px-2 py-1.5 rounded-md text-white text-xs ml-6" style="background: var(--gradient-primary);">"Te envio cotizacion + link de pago 🚀"</div>
                        </div>

                        <!-- AI suggestion -->
                        <div class="rounded-lg p-2.5 border border-violet-500/30" style="background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15));">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🤖</span>
                                <div>
                                    <div class="text-[10px] font-semibold text-cyan-300">IA Vendedor</div>
                                    <div class="text-[10px] text-slate-300">"Cliente con score 92. Generando link Stripe..."</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating cards -->
                <div class="absolute -left-20 top-12 p-3 rounded-xl flex items-center gap-2 float-1 shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/30 flex items-center justify-center">✓</div>
                    <div>
                        <div class="text-[11px] font-semibold text-white">Venta cerrada</div>
                        <div class="text-[9px] text-slate-400">$1,500 · IA</div>
                    </div>
                </div>
                <div class="absolute -right-12 top-1/2 p-3 rounded-xl flex items-center gap-2 float-2 shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#25D366,#10B981);">
                        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-white">Cloud API</div>
                        <div class="text-[9px] text-slate-400">Verified · ●</div>
                    </div>
                </div>
                <div class="absolute -left-10 -bottom-4 p-3 rounded-xl flex items-center gap-2 float-3 shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">⚡</div>
                    <div>
                        <div class="text-[11px] font-semibold text-white">3 numeros</div>
                        <div class="text-[9px] text-slate-400">Operando</div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="flex items-center gap-6 mt-16">
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-0.5">10M+</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wider">Mensajes</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-0.5">98%</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wider">Respuesta</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-0.5">3.5x</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wider">Conversion</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="text-3xl font-bold gradient-text-aurora mb-0.5">30+</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wider">Integraciones</div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
