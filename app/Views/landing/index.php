<?php
/** @var array $plans */
\App\Core\View::extend('layouts.landing');
\App\Core\View::start('content');
?>

<!-- ============================================ -->
<!-- NAV -->
<!-- ============================================ -->
<nav id="mainNav" x-data="{ open: false }" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="flex items-center gap-2.5 group">
            <div class="relative">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-violet-500/30 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="absolute -inset-1 bg-gradient-to-br from-violet-500 to-cyan-400 rounded-xl blur opacity-30 group-hover:opacity-50 transition"></div>
            </div>
            <span class="text-lg font-bold text-white tracking-tight">Kyros<span class="gradient-text">Pulse</span></span>
        </a>

        <div class="hidden lg:flex items-center gap-1 text-sm">
            <a href="#features" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Producto</a>
            <a href="#how" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Como funciona</a>
            <a href="#integrations" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Integraciones</a>
            <a href="#pricing" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Precios</a>
            <a href="#faq" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Preguntas</a>
        </div>

        <div class="hidden md:flex items-center gap-2">
            <a href="<?= url('/login') ?>" class="text-sm text-slate-300 hover:text-white px-4 py-2 transition">Iniciar sesion</a>
            <a href="<?= url('/register') ?>" class="btn btn-primary btn-sm">
                Empezar gratis
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>

        <button @click="open = !open" class="lg:hidden text-white p-2 -mr-2">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div x-show="open" x-transition x-cloak class="lg:hidden border-t border-white/5 bg-[#050817]/95 backdrop-blur-xl">
        <div class="px-6 py-4 space-y-1">
            <a href="#features" class="block py-2 text-slate-300">Producto</a>
            <a href="#how" class="block py-2 text-slate-300">Como funciona</a>
            <a href="#pricing" class="block py-2 text-slate-300">Precios</a>
            <div class="pt-3 border-t border-white/5 mt-3 flex gap-2">
                <a href="<?= url('/login') ?>" class="flex-1 btn btn-secondary btn-sm">Iniciar sesion</a>
                <a href="<?= url('/register') ?>" class="flex-1 btn btn-primary btn-sm">Empezar</a>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- HERO -->
<!-- ============================================ -->
<section class="relative pt-32 pb-24 lg:pt-40 lg:pb-32 overflow-hidden">
    <!-- Background mesh -->
    <div class="absolute inset-0 bg-mesh"></div>
    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-60"></div>

    <!-- Animated orbs -->
    <div class="absolute top-20 -left-20 w-96 h-96 bg-violet-600 rounded-full blur-[120px] opacity-30 animate-float"></div>
    <div class="absolute bottom-20 -right-20 w-96 h-96 bg-cyan-500 rounded-full blur-[120px] opacity-25 animate-float" style="animation-delay: -3s"></div>

    <div class="relative max-w-7xl mx-auto px-6">
        <div class="max-w-4xl mx-auto text-center mb-16">
            <!-- Pill badge -->
            <div class="reveal inline-flex items-center gap-2 px-3 py-1.5 mb-8 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
                <span class="live-dot"></span>
                <span class="text-xs font-medium text-slate-300">
                    <span class="text-cyan-400">Nuevo</span> · Potenciado por Claude Sonnet 6
                </span>
                <span class="kbd ml-1">v1.0</span>
            </div>

            <h1 class="reveal heading-xl text-white text-balance mb-8">
                El sistema operativo
                <span class="block">de tu <span class="gradient-text-aurora">negocio digital</span></span>
            </h1>
            <p class="reveal reveal-delay-1 text-lg lg:text-xl text-slate-400 leading-relaxed max-w-2xl mx-auto mb-10 text-balance">
                CRM, bandeja WhatsApp multiagente, automatizaciones e IA en una sola plataforma.
                Convierte cada conversacion en una venta.
            </p>

            <div class="reveal reveal-delay-2 flex flex-col sm:flex-row items-center justify-center gap-3 mb-10">
                <a href="<?= url('/register') ?>" class="btn btn-glow btn-lg">
                    Empezar gratis 14 dias
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                <a href="https://wa.me/18095555555?text=Hola%20Kyros%20Pulse,%20quiero%20una%20demo" target="_blank" class="btn btn-secondary btn-lg">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    Solicitar demo
                </a>
            </div>

            <div class="reveal reveal-delay-3 flex items-center justify-center gap-6 text-xs text-slate-500">
                <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Sin tarjeta de credito</div>
                <div class="hidden sm:flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Setup en 5 minutos</div>
                <div class="hidden sm:flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Cancela cuando quieras</div>
            </div>
        </div>

        <!-- Hero mockup -->
        <div class="reveal max-w-6xl mx-auto relative">
            <div class="absolute -inset-4 bg-gradient-to-r from-violet-600 to-cyan-500 rounded-3xl blur-3xl opacity-25"></div>
            <div class="browser-chrome relative">
                <div class="browser-chrome-bar">
                    <span class="browser-dot bg-rose-500/70"></span>
                    <span class="browser-dot bg-amber-500/70"></span>
                    <span class="browser-dot bg-emerald-500/70"></span>
                    <div class="ml-3 flex-1 max-w-md mx-auto">
                        <div class="px-3 py-1 rounded-md bg-white/5 text-xs text-slate-400 font-mono text-center flex items-center gap-2 justify-center">
                            <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            app.kyrosrd.com/dashboard
                        </div>
                    </div>
                </div>
                <div class="bg-[#0A0F25] p-1">
                    <!-- Dashboard mockup -->
                    <div class="grid grid-cols-12 gap-1 min-h-[480px]">
                        <!-- Sidebar -->
                        <div class="col-span-3 bg-[#0F1530] p-4 rounded-l-xl">
                            <div class="flex items-center gap-2 mb-6">
                                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-violet-500 to-cyan-400"></div>
                                <span class="font-bold text-sm text-white">Kyros<span class="gradient-text">Pulse</span></span>
                            </div>
                            <div class="space-y-1">
                                <?php $items = [
                                    ['📊', 'Dashboard', true],
                                    ['👥', 'Contactos', false],
                                    ['🎯', 'Pipeline', false],
                                    ['💬', 'Bandeja', false],
                                    ['🎫', 'Tickets', false],
                                    ['⚡', 'Automatizaciones', false],
                                    ['📈', 'Reportes', false],
                                ]; foreach ($items as [$icon, $label, $active]): ?>
                                <div class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-xs <?= $active ? 'bg-violet-500/20 text-white' : 'text-slate-400' ?>">
                                    <span class="opacity-70"><?= $icon ?></span>
                                    <span><?= $label ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Main -->
                        <div class="col-span-9 p-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Dashboard</div>
                                    <div class="font-bold text-white text-sm">Resumen de hoy</div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="live-dot"></span>
                                    <span class="text-[10px] text-emerald-400">En vivo</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <?php foreach ([['Mensajes', '1,247', '+12%', '#7C3AED'], ['Leads', '83', '+24%', '#06B6D4'], ['Conv.', '241', '+8%', '#10B981'], ['Ventas', '$18.4k', '+38%', '#F59E0B']] as $card): ?>
                                <div class="bg-white/5 rounded-lg p-2.5 border border-white/5">
                                    <div class="text-[9px] text-slate-500 uppercase tracking-wider"><?= $card[0] ?></div>
                                    <div class="font-bold text-white text-base mt-0.5"><?= $card[1] ?></div>
                                    <div class="text-[9px] flex items-center gap-1 mt-0.5" style="color: <?= $card[3] ?>"><svg class="w-2 h-2" fill="currentColor" viewBox="0 0 12 12"><path d="M6 2l4 4H8v4H4V6H2z"/></svg> <?= $card[2] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Chart mockup -->
                            <div class="bg-white/5 rounded-lg p-3 border border-white/5">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[10px] text-slate-400">Mensajes ultimos 14 dias</div>
                                    <div class="flex gap-2 text-[9px]">
                                        <span class="flex items-center gap-1 text-slate-400"><span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>Recibidos</span>
                                        <span class="flex items-center gap-1 text-slate-400"><span class="w-1.5 h-1.5 rounded-full bg-cyan-500"></span>Enviados</span>
                                    </div>
                                </div>
                                <svg viewBox="0 0 300 80" class="w-full h-20">
                                    <defs>
                                        <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.4"/>
                                            <stop offset="100%" stop-color="#7C3AED" stop-opacity="0"/>
                                        </linearGradient>
                                        <linearGradient id="g2" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#06B6D4" stop-opacity="0.4"/>
                                            <stop offset="100%" stop-color="#06B6D4" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M0,60 L25,52 L50,55 L75,40 L100,45 L125,30 L150,35 L175,25 L200,28 L225,18 L250,22 L275,12 L300,15 L300,80 L0,80 Z" fill="url(#g1)"/>
                                    <path d="M0,60 L25,52 L50,55 L75,40 L100,45 L125,30 L150,35 L175,25 L200,28 L225,18 L250,22 L275,12 L300,15" fill="none" stroke="#7C3AED" stroke-width="2"/>
                                    <path d="M0,70 L25,65 L50,68 L75,55 L100,58 L125,45 L150,48 L175,40 L200,42 L225,32 L250,38 L275,28 L300,30 L300,80 L0,80 Z" fill="url(#g2)"/>
                                    <path d="M0,70 L25,65 L50,68 L75,55 L100,58 L125,45 L150,48 L175,40 L200,42 L225,32 L250,38 L275,28 L300,30" fill="none" stroke="#06B6D4" stroke-width="2"/>
                                </svg>
                            </div>
                            <!-- Conversations -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-white/5 rounded-lg p-2.5 border border-white/5">
                                    <div class="text-[10px] text-slate-400 mb-2">Conversaciones recientes</div>
                                    <div class="space-y-1.5">
                                        <?php foreach ([['M', 'Maria L.', 'Estoy interesada...', '#7C3AED'], ['J', 'Juan P.', 'Necesito factura', '#06B6D4']] as $c): ?>
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[8px] font-bold flex-shrink-0" style="background: <?= $c[3] ?>"><?= $c[0] ?></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[10px] text-white font-medium truncate"><?= $c[1] ?></div>
                                                <div class="text-[8px] text-slate-500 truncate"><?= $c[2] ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-br from-violet-500/20 to-cyan-500/20 rounded-lg p-2.5 border border-violet-500/30">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="text-base">🤖</span>
                                        <span class="text-[10px] text-cyan-300 font-semibold">IA Sugiere</span>
                                    </div>
                                    <div class="text-[10px] text-slate-300 leading-snug">"Maria muestra alta intencion. Envia cotizacion ahora."</div>
                                    <button class="mt-1.5 text-[9px] px-2 py-0.5 rounded bg-white/10 text-white">Aplicar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating cards -->
            <div class="hidden md:block absolute -left-12 top-1/3 surface-elevated p-3 rounded-xl flex items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                <div class="w-9 h-9 rounded-lg bg-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <div class="text-xs font-semibold text-white">Lead ganado</div>
                    <div class="text-[10px] text-slate-400">$2,400 · ahora</div>
                </div>
            </div>
            <div class="hidden md:flex absolute -right-12 top-2/3 surface-elevated p-3 rounded-xl items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10" style="animation-delay: -2s">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-violet-500 to-cyan-500 flex items-center justify-center">
                    <span class="text-base">🤖</span>
                </div>
                <div>
                    <div class="text-xs font-semibold text-white">IA respondio</div>
                    <div class="text-[10px] text-slate-400">15 mensajes auto</div>
                </div>
            </div>
        </div>

        <!-- Trust marquee -->
        <div class="mt-20 reveal">
            <p class="text-center text-xs uppercase tracking-widest text-slate-500 mb-6">Confiado por equipos en mas de 12 paises</p>
            <div class="marquee-paused overflow-hidden">
                <div class="marquee gap-12 items-center opacity-50 hover:opacity-80 transition">
                    <?php $brands = ['Inmobiliaria','Clinica Vital','Auto Trust','Fashion Plus','TechStore','EduOnline','Restobar','HealthCare','Logistics Pro','SmartFin']; foreach (array_merge($brands, $brands) as $brand): ?>
                    <div class="flex-shrink-0 text-2xl font-bold text-slate-500 whitespace-nowrap"><?= e($brand) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- STATS / SOCIAL PROOF -->
<!-- ============================================ -->
<section class="relative py-20 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <?php foreach ([
                ['10M+', 'mensajes procesados', '10000000'],
                ['98', 'tasa de respuesta %', '98', 'suffix' => '%'],
                ['3.5x', 'aumento conversion', '3.5', 'decimals' => 1, 'suffix' => 'x'],
                ['<1s', 'tiempo respuesta IA', '1', 'suffix' => 's', 'prefix' => '<'],
            ] as $stat): ?>
            <div class="reveal">
                <div class="heading-md gradient-text-aurora mb-2">
                    <?php if (!empty($stat['prefix'])): ?><?= $stat['prefix'] ?><?php endif; ?>
                    <span data-count="<?= $stat[2] ?>" data-decimals="<?= $stat['decimals'] ?? 0 ?>" data-suffix="<?= $stat['suffix'] ?? '' ?>">0</span>
                </div>
                <p class="text-sm text-slate-400"><?= e($stat[1]) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- BENTO FEATURES -->
<!-- ============================================ -->
<section id="features" class="relative py-24 lg:py-32">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-violet-500/10 border border-violet-500/20">
                <span class="text-xs font-semibold text-violet-300">PLATAFORMA</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-5 text-balance">
                Todo lo que necesitas, <span class="gradient-text">en un solo lugar</span>
            </h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">
                Una plataforma todo-en-uno construida para equipos modernos. Reemplaza 5 herramientas con Kyros Pulse.
            </p>
        </div>

        <!-- Bento grid -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 auto-rows-[minmax(220px,auto)]">
            <!-- BIG: Inbox WhatsApp -->
            <div class="md:col-span-4 md:row-span-2 reveal spotlight-card surface p-8 lg:p-10 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="inline-flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                        </div>
                        <span class="badge badge-emerald">Real-time</span>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3">Bandeja WhatsApp multiagente</h3>
                    <p class="text-slate-400 mb-6 max-w-md">
                        Atiende como WhatsApp Business, pero con superpoderes: asignaciones, plantillas, IA contextual y notas internas.
                    </p>
                    <!-- Mini chat preview -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="surface-elevated p-3 rounded-lg border border-white/5">
                            <div class="text-[10px] text-slate-500 mb-2">3 conversaciones en cola</div>
                            <div class="space-y-2">
                                <?php foreach ([['M', 'Maria', '#7C3AED'], ['J', 'Juan', '#06B6D4'], ['A', 'Ana', '#10B981']] as $c): ?>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold" style="background: <?= $c[2] ?>"><?= $c[0] ?></div>
                                    <div class="flex-1 h-1.5 bg-white/5 rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-violet-500 to-cyan-500" style="width: <?= rand(30, 90) ?>%"></div></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="surface-elevated p-2 rounded-lg border border-white/5 flex items-center gap-2">
                                <span class="text-base">📎</span>
                                <span class="text-xs text-slate-300">Archivos</span>
                            </div>
                            <div class="surface-elevated p-2 rounded-lg border border-white/5 flex items-center gap-2">
                                <span class="text-base">⚡</span>
                                <span class="text-xs text-slate-300">Respuestas rapidas</span>
                            </div>
                            <div class="surface-elevated p-2 rounded-lg border border-white/5 flex items-center gap-2">
                                <span class="text-base">📌</span>
                                <span class="text-xs text-slate-300">Notas internas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IA Card -->
            <div class="md:col-span-2 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="absolute top-0 right-0 w-64 h-64 bg-violet-500/10 rounded-full blur-3xl"></div>
                <div class="relative h-full flex flex-col">
                    <div class="w-10 h-10 rounded-xl bg-violet-500/15 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">IA Claude Sonnet 6</h3>
                    <p class="text-sm text-slate-400 mb-4 flex-1">Sugerencias, scoring de leads, respuestas automaticas y resumenes contextuales.</p>
                    <div class="surface-elevated p-2.5 rounded-lg border border-violet-500/20 bg-violet-500/5">
                        <div class="text-[10px] text-violet-300 mb-1">🤖 Sugerencia IA</div>
                        <div class="text-xs text-slate-300">"Lead caliente. Sugerir cierre."</div>
                    </div>
                </div>
            </div>

            <!-- Pipeline kanban -->
            <div class="md:col-span-2 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Pipeline kanban</h3>
                <p class="text-sm text-slate-400 mb-4">Drag & drop entre etapas. Score IA y probabilidad ponderada.</p>
                <div class="flex gap-1.5">
                    <div class="flex-1 bg-white/5 rounded p-1.5 border-t-2 border-cyan-500"><div class="text-[8px] text-slate-400">12 leads</div></div>
                    <div class="flex-1 bg-white/5 rounded p-1.5 border-t-2 border-violet-500"><div class="text-[8px] text-slate-400">8 leads</div></div>
                    <div class="flex-1 bg-white/5 rounded p-1.5 border-t-2 border-emerald-500"><div class="text-[8px] text-slate-400">3 leads</div></div>
                </div>
            </div>

            <!-- Automatizaciones -->
            <div class="md:col-span-3 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Automatizaciones visuales</h3>
                <p class="text-sm text-slate-400 mb-4">Reglas con disparadores, condiciones y acciones encadenadas. Bot 24/7 con IA.</p>
                <div class="flex items-center gap-1.5 text-[10px]">
                    <span class="badge badge-cyan">Cuando</span>
                    <span class="text-slate-500">→</span>
                    <span class="badge badge-amber">Si</span>
                    <span class="text-slate-500">→</span>
                    <span class="badge badge-emerald">Hacer</span>
                </div>
            </div>

            <!-- Reportes -->
            <div class="md:col-span-3 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-rose-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Reportes en tiempo real</h3>
                <p class="text-sm text-slate-400 mb-4">Conversion, sentimiento, tasa de respuesta y rendimiento por agente.</p>
                <div class="flex items-end gap-1 h-12">
                    <?php for ($i = 0; $i < 14; $i++): ?>
                    <div class="flex-1 rounded-t bg-gradient-to-t from-violet-500/40 to-cyan-500/40" style="height: <?= rand(30, 100) ?>%"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Multi-tenant -->
            <div class="md:col-span-2 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-1">Multi-tenant</h3>
                <p class="text-sm text-slate-400">Cada empresa aislada. Sin riesgo de mezclas.</p>
            </div>

            <!-- Tickets / Tareas -->
            <div class="md:col-span-2 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-pink-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-1">Tickets & SLA</h3>
                <p class="text-sm text-slate-400">Soporte multicanal con prioridad y vencimientos.</p>
            </div>

            <!-- Campanas -->
            <div class="md:col-span-2 reveal spotlight-card surface p-6 relative overflow-hidden border-white/5 bg-white/[0.03]">
                <div class="w-10 h-10 rounded-xl bg-orange-500/15 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-1">Campanas masivas</h3>
                <p class="text-sm text-slate-400">Segmentos dinamicos + plantillas aprobadas.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- HOW IT WORKS -->
<!-- ============================================ -->
<section id="how" class="relative py-24 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-cyan-500/10 border border-cyan-500/20">
                <span class="text-xs font-semibold text-cyan-300">PROCESO</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-5 text-balance">En 3 pasos estas vendiendo mas</h2>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <?php foreach ([
                ['01', 'Crea tu cuenta', 'Registro en menos de 2 minutos. 14 dias gratis sin tarjeta.', '🚀'],
                ['02', 'Conecta WhatsApp', 'Pega tu API key Wasapi y empieza a recibir conversaciones.', '🔌'],
                ['03', 'Activa la IA', 'Entrena tu asistente con tu negocio y deja que automatice.', '🤖'],
            ] as $i => $step): ?>
            <div class="reveal relative">
                <?php if ($i < 2): ?>
                <div class="hidden md:block absolute top-12 left-full w-12 h-px -translate-x-6">
                    <svg viewBox="0 0 48 2" class="w-full h-full"><line x1="0" y1="1" x2="48" y2="1" stroke="url(#sg)" stroke-width="2" stroke-dasharray="4 4"/><defs><linearGradient id="sg"><stop offset="0%" stop-color="#7C3AED"/><stop offset="100%" stop-color="#06B6D4"/></linearGradient></defs></svg>
                </div>
                <?php endif; ?>
                <div class="surface p-8 h-full hover-lift border-white/5 bg-white/[0.03] relative overflow-hidden">
                    <div class="text-7xl absolute -top-2 -right-2 opacity-10"><?= $step[3] ?></div>
                    <div class="text-6xl font-black gradient-text-aurora mb-4 leading-none"><?= $step[0] ?></div>
                    <h3 class="text-xl font-bold text-white mb-2"><?= e($step[1]) ?></h3>
                    <p class="text-slate-400"><?= e($step[2]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- INTEGRATIONS -->
<!-- ============================================ -->
<section id="integrations" class="relative py-24 border-t border-white/5 overflow-hidden">
    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-50"></div>
    <div class="relative max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                <span class="text-xs font-semibold text-emerald-300">INTEGRACIONES</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-5 text-balance">Conectado a las <span class="gradient-text">mejores herramientas</span></h2>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <!-- Wasapi -->
            <div class="reveal surface p-7 border-white/5 bg-white/[0.03] hover-lift relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/8 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/15 flex items-center justify-center">
                            <svg class="w-7 h-7 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                        </div>
                        <span class="badge badge-emerald badge-dot">Active</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Wasapi WhatsApp</h3>
                    <div class="text-xs text-slate-500 font-mono mb-4">api-ws.wasapi.io/v1</div>
                    <p class="text-sm text-slate-400 mb-5">API oficial integrada para enviar y recibir mensajes en tiempo real.</p>
                    <ul class="space-y-2 text-sm">
                        <?php foreach (['Webhooks en tiempo real', 'Plantillas aprobadas', 'Multi-numero', 'Status de entrega'] as $item): ?>
                        <li class="flex items-center gap-2 text-slate-300">
                            <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= $item ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Claude IA -->
            <div class="reveal surface p-7 border-white/5 bg-white/[0.03] hover-lift relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-violet-500/8 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-14 h-14 rounded-2xl bg-violet-500/15 flex items-center justify-center relative">
                            <svg class="w-7 h-7 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <div class="absolute inset-0 rounded-2xl bg-violet-500/20 animate-ping"></div>
                        </div>
                        <span class="badge badge-primary">v6.0</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Claude Sonnet 6</h3>
                    <div class="text-xs text-slate-500 font-mono mb-4">api.anthropic.com</div>
                    <p class="text-sm text-slate-400 mb-5">El motor de IA que convierte conversaciones en oportunidades.</p>
                    <ul class="space-y-2 text-sm">
                        <?php foreach (['Sugerencias contextuales', 'Calificacion automatica', 'Entrenamiento por empresa', 'Multi-idioma'] as $item): ?>
                        <li class="flex items-center gap-2 text-slate-300">
                            <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= $item ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Resend -->
            <div class="reveal surface p-7 border-white/5 bg-white/[0.03] hover-lift relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-500/8 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-14 h-14 rounded-2xl bg-cyan-500/15 flex items-center justify-center">
                            <svg class="w-7 h-7 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="badge badge-cyan">99.9%</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Resend Email</h3>
                    <div class="text-xs text-slate-500 font-mono mb-4">api.resend.com</div>
                    <p class="text-sm text-slate-400 mb-5">Correos transaccionales con entregabilidad de clase mundial.</p>
                    <ul class="space-y-2 text-sm">
                        <?php foreach (['Plantillas HTML modernas', 'Verificacion de cuenta', 'Reportes y alertas', 'Email comercial'] as $item): ?>
                        <li class="flex items-center gap-2 text-slate-300">
                            <svg class="w-4 h-4 text-cyan-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= $item ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- INDUSTRIES -->
<!-- ============================================ -->
<section class="relative py-24 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <h2 class="reveal heading-md text-white mb-3">Para cualquier negocio que vende y atiende</h2>
            <p class="reveal text-slate-400">Desde clinicas hasta inmobiliarias, centros educativos hasta retail.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            <?php foreach ([
                ['🏥', 'Clinicas'],
                ['🏘️', 'Inmobiliarias'],
                ['🛍️', 'Tiendas'],
                ['🎓', 'Colegios'],
                ['🔧', 'Servicios'],
                ['📞', 'Call centers'],
                ['🍽️', 'Restaurantes'],
                ['🚗', 'Concesionarios'],
            ] as $industry): ?>
            <div class="reveal surface p-4 text-center hover-lift border-white/5 bg-white/[0.03] cursor-pointer">
                <div class="text-3xl mb-2"><?= $industry[0] ?></div>
                <div class="text-xs font-medium text-slate-300"><?= e($industry[1]) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- PRICING -->
<!-- ============================================ -->
<section id="pricing" class="relative py-24 lg:py-32 border-t border-white/5" x-data="{ yearly: false }">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-amber-500/10 border border-amber-500/20">
                <span class="text-xs font-semibold text-amber-300">PRECIOS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Planes que <span class="gradient-text">crecen contigo</span></h2>
            <p class="reveal text-lg text-slate-400 mb-8">Empieza gratis. Cambia o cancela cuando quieras.</p>

            <!-- Toggle -->
            <div class="reveal inline-flex items-center gap-3 p-1 rounded-full bg-white/5 border border-white/10">
                <button @click="yearly = false" :class="!yearly ? 'bg-white/10 text-white' : 'text-slate-400'" class="px-4 py-1.5 rounded-full text-sm font-medium transition">Mensual</button>
                <button @click="yearly = true" :class="yearly ? 'bg-white/10 text-white' : 'text-slate-400'" class="px-4 py-1.5 rounded-full text-sm font-medium transition">
                    Anual
                    <span class="ml-1 badge badge-emerald">-20%</span>
                </button>
            </div>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            $defaultPlans = [
                ['Starter', 'Para emprendedores', '0', '0',
                    [['1 usuario'], ['500 contactos'], ['500 mensajes'], ['Bandeja WhatsApp'], ['Soporte por email']],
                    false, false],
                ['Professional', 'Pequenas empresas', '49', '470',
                    [['5 usuarios'], ['5,000 contactos'], ['5,000 mensajes'], ['IA Claude Sonnet 6', true], ['Automatizaciones', true], ['Soporte chat']],
                    true, false],
                ['Business', 'Empresas en crecimiento', '129', '1240',
                    [['20 usuarios'], ['50,000 contactos'], ['50,000 mensajes'], ['IA Claude'], ['Reportes avanzados', true], ['API access', true], ['Soporte prioritario']],
                    false, false],
                ['Enterprise', 'Grandes operaciones', '349', '3350',
                    [['Usuarios ilimitados'], ['Contactos ilimitados'], ['Mensajes ilimitados'], ['IA + API'], ['Soporte dedicado', true], ['SLA 99.9%']],
                    false, true],
            ];
            $plansToShow = !empty($plans) ? array_map(function ($p) {
                $features = [];
                if (!empty($p['max_users']))    $features[] = ["{$p['max_users']} usuarios"];
                if (!empty($p['max_contacts'])) $features[] = ["{$p['max_contacts']} contactos"];
                if (!empty($p['max_messages'])) $features[] = ["{$p['max_messages']} mensajes"];
                if ($p['ai_enabled'])           $features[] = ['IA Claude Sonnet 6', true];
                if ($p['advanced_reports'])     $features[] = ['Reportes avanzados', true];
                if ($p['api_access'])           $features[] = ['API access', true];
                $features[] = ['Soporte ' . $p['support_level']];
                return [
                    $p['name'], $p['description'] ?? '',
                    number_format((float) $p['price_monthly'], 0),
                    number_format((float) $p['price_yearly'],  0),
                    $features, false, $p['slug'] === 'enterprise',
                ];
            }, $plans) : $defaultPlans;

            // Marcar el segundo plan como featured
            if (count($plansToShow) > 1) {
                $plansToShow[1][5] = true;
            }

            foreach ($plansToShow as $i => [$name, $desc, $monthly, $yearly, $features, $featured, $isEnterprise]):
            ?>
            <div class="reveal reveal-delay-<?= min(3, $i) ?> relative <?= $featured ? 'lg:scale-105 z-10' : '' ?>">
                <?php if ($featured): ?>
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                    <span class="badge badge-primary px-3 py-1 text-[10px]">⭐ MAS POPULAR</span>
                </div>
                <?php endif; ?>

                <div class="<?= $featured ? 'gradient-border p-px' : '' ?> rounded-2xl h-full">
                    <div class="surface p-7 h-full <?= $featured ? 'bg-[#0F1530]/95' : 'bg-white/[0.02]' ?> border-white/5 flex flex-col relative overflow-hidden">
                        <?php if ($featured): ?>
                        <div class="absolute top-0 right-0 w-48 h-48 bg-violet-500/10 rounded-full blur-3xl"></div>
                        <?php endif; ?>

                        <div class="relative">
                            <h3 class="text-lg font-bold text-white mb-1"><?= e($name) ?></h3>
                            <p class="text-sm text-slate-400 mb-6"><?= e((string) $desc) ?></p>

                            <div class="mb-6">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-5xl font-black text-white">$<span x-text="yearly ? <?= (int) ((float) $yearly / 12) ?> : <?= (int) $monthly ?>"><?= e($monthly) ?></span></span>
                                    <span class="text-slate-400 text-sm">/mes</span>
                                </div>
                                <div x-show="yearly" class="text-xs text-emerald-400 mt-1">$<?= e($yearly) ?> facturado anualmente</div>
                            </div>

                            <ul class="space-y-2.5 mb-7 flex-1">
                                <?php foreach ($features as $feat):
                                    $featText = $feat[0];
                                    $highlight = !empty($feat[1]);
                                ?>
                                <li class="flex items-start gap-2 text-sm <?= $highlight ? 'text-white font-medium' : 'text-slate-300' ?>">
                                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0 <?= $highlight ? 'text-violet-400' : 'text-slate-500' ?>" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <?= e($featText) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <a href="<?= $isEnterprise ? '#' : url('/register') ?>" class="<?= $featured ? 'btn btn-primary' : 'btn btn-secondary' ?> w-full justify-center">
                                <?= $isEnterprise ? 'Hablar con ventas' : ($monthly === '0' ? 'Empezar gratis' : 'Empezar ahora') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- TESTIMONIAL CTA -->
<!-- ============================================ -->
<section class="relative py-24 border-t border-white/5">
    <div class="max-w-5xl mx-auto px-6">
        <div class="reveal surface relative overflow-hidden p-10 lg:p-16 text-center border-white/5 bg-gradient-to-br from-violet-500/5 to-cyan-500/5">
            <div class="absolute inset-0 bg-mesh opacity-50"></div>
            <div class="absolute -top-20 -left-20 w-80 h-80 bg-violet-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-cyan-500/20 rounded-full blur-3xl"></div>

            <div class="relative">
                <div class="text-5xl mb-6 opacity-50">"</div>
                <p class="text-xl lg:text-2xl text-white font-medium mb-8 leading-relaxed text-balance">
                    Pasamos de responder en horas a responder en minutos. La IA califica los leads automaticamente y nuestro equipo se concentra solo en los que tienen alta probabilidad de cerrar.
                </p>
                <div class="flex items-center justify-center gap-4">
                    <div class="avatar avatar-lg">CM</div>
                    <div class="text-left">
                        <div class="font-bold text-white">Carolina Mendez</div>
                        <div class="text-sm text-slate-400">CEO, Inmobiliaria Premium</div>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1 mt-4">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECURITY -->
<!-- ============================================ -->
<section class="relative py-24 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="reveal">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                    <span class="text-xs font-semibold text-emerald-300">SEGURIDAD</span>
                </div>
                <h2 class="heading-lg text-white mb-6 text-balance">Datos protegidos a nivel <span class="gradient-text">enterprise</span></h2>
                <p class="text-lg text-slate-400 mb-8">Construido sobre las mejores practicas de la industria. Tus clientes son tuyos.</p>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ([
                        ['Cifrado en transito', '🔒'],
                        ['Hash bcrypt', '🔑'],
                        ['CSRF en forms', '🛡️'],
                        ['Rate limiting', '🚦'],
                        ['Prepared statements', '💾'],
                        ['Auditoria completa', '📋'],
                    ] as $item): ?>
                    <div class="surface p-3 flex items-center gap-2.5 border-white/5 bg-white/[0.03]">
                        <span class="text-lg"><?= $item[1] ?></span>
                        <span class="text-sm text-slate-300"><?= e($item[0]) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="reveal relative">
                <div class="surface p-12 text-center border-white/5 bg-gradient-to-br from-emerald-500/5 to-cyan-500/5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-30"></div>
                    <div class="relative">
                        <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-emerald-400 to-cyan-500 mx-auto mb-6 flex items-center justify-center pulse-ring">
                            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div class="heading-md gradient-text-aurora mb-2">100% aislado</div>
                        <p class="text-slate-400">Cada empresa tiene su propio espacio. <strong class="text-white">Imposible mezclar datos</strong> entre tenants.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FAQ -->
<!-- ============================================ -->
<section id="faq" class="relative py-24 border-t border-white/5" x-data="{ open: 0 }">
    <div class="max-w-3xl mx-auto px-6">
        <div class="text-center mb-12">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-rose-500/10 border border-rose-500/20">
                <span class="text-xs font-semibold text-rose-300">FAQ</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4">Preguntas frecuentes</h2>
        </div>

        <div class="space-y-3">
            <?php foreach ([
                ['Necesito tener WhatsApp Business API ya contratado?', 'No es obligatorio. Puedes empezar con tu cuenta de Wasapi y conectarla con tu API key. Te guiamos paso a paso desde el panel de configuracion.'],
                ['La IA esta incluida en todos los planes?', 'La IA con Claude Sonnet 6 esta disponible desde el plan Professional. El plan Starter te permite probar el CRM y la bandeja sin IA.'],
                ['Puedo migrar mis contactos desde otro CRM?', 'Si. Soportamos importacion CSV con encabezados en espanol o ingles, y exportacion completa en cualquier momento.'],
                ['Que tan seguro es el sistema?', 'Aplicamos hash bcrypt para contrasenas, prepared statements en todas las queries, tokens CSRF, rate limiting, sesiones con HttpOnly y SameSite, registro de auditoria completo y aislamiento estricto multi-tenant.'],
                ['Puedo cancelar cuando quiera?', 'Si, sin contratos. Cancela tu suscripcion desde tu panel y conserva acceso hasta el fin del periodo facturado.'],
                ['Cuanto tarda la configuracion?', 'En 5 minutos puedes tener tu cuenta lista, conectado WhatsApp via Wasapi y enviando tu primer mensaje desde Kyros Pulse.'],
            ] as $i => $faq): ?>
            <div class="reveal surface border-white/5 bg-white/[0.03] overflow-hidden">
                <button @click="open = open === <?= $i ?> ? -1 : <?= $i ?>" class="w-full p-5 text-left flex items-center justify-between gap-4 hover:bg-white/5 transition">
                    <span class="font-semibold text-white pr-4"><?= e($faq[0]) ?></span>
                    <div class="w-7 h-7 rounded-full bg-white/5 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open === <?= $i ?> }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div x-show="open === <?= $i ?>" x-collapse class="px-5 pb-5 text-slate-400 text-sm leading-relaxed">
                    <?= e($faq[1]) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FINAL CTA -->
<!-- ============================================ -->
<section class="relative py-32 overflow-hidden border-t border-white/5">
    <div class="absolute inset-0 bg-mesh opacity-60"></div>
    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-40"></div>
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-violet-500/20 rounded-full blur-3xl"></div>

    <div class="relative max-w-4xl mx-auto px-6 text-center reveal">
        <h2 class="heading-lg text-white mb-6 text-balance">
            Convierte cada conversacion <br class="hidden sm:block">en una <span class="gradient-text-aurora">oportunidad</span>
        </h2>
        <p class="text-xl text-slate-300 mb-10">Empieza tu prueba gratis hoy. Sin tarjeta. Sin compromiso.</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= url('/register') ?>" class="btn btn-glow btn-lg">
                Empezar gratis 14 dias
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="https://wa.me/18095555555" target="_blank" class="btn btn-secondary btn-lg">
                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                Hablar por WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FOOTER -->
<!-- ============================================ -->
<footer class="relative py-12 border-t border-white/5 bg-[#030611]">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid md:grid-cols-5 gap-8 mb-10">
            <div class="md:col-span-2">
                <a href="<?= url('/') ?>" class="inline-flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-bold text-white">Kyros<span class="gradient-text">Pulse</span></span>
                </a>
                <p class="text-sm text-slate-400 max-w-xs leading-relaxed">El sistema operativo de tu negocio digital. CRM, WhatsApp, IA y automatizaciones en una sola plataforma.</p>
                <div class="mt-4 flex gap-2">
                    <span class="badge badge-emerald badge-dot">Sistema operativo</span>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-white text-sm mb-4">Producto</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#features" class="text-slate-400 hover:text-white transition">Funcionalidades</a></li>
                    <li><a href="#pricing" class="text-slate-400 hover:text-white transition">Planes</a></li>
                    <li><a href="#integrations" class="text-slate-400 hover:text-white transition">Integraciones</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white text-sm mb-4">Empresa</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Sobre Kyros</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Blog</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Contacto</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white text-sm mb-4">Legal</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Privacidad</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Terminos</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition">Cookies</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 pt-6 flex flex-col md:flex-row justify-between items-center gap-3">
            <span class="text-xs text-slate-500">&copy; <?= date('Y') ?> Kyros Pulse. Todos los derechos reservados.</span>
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="live-dot"></span>Todos los sistemas operativos</span>
            </div>
        </div>
    </div>
</footer>

<?php \App\Core\View::stop(); ?>
