<?php
/** @var array $plans */
/** @var array $changelog */
/** @var array $branding */
\App\Core\View::extend('layouts.landing');
\App\Core\View::start('content');

$g = fn(string $k, string $d = '') => (string) ($branding[$k] ?? $d);

$brand     = $g('brand_name', 'Kyros Pulse');
$tagline   = $g('brand_tagline', 'El sistema operativo de tu negocio digital');
$eyebrow   = $g('hero_eyebrow', 'Customer Engagement Platform · WhatsApp Business · IA');
$headline  = $g('hero_headline', 'La plataforma todo-en-uno para mensajeria, CRM y contact center con IA');
$heroSub   = $g('hero_sub', 'Atiende a tus clientes desde multiples numeros de WhatsApp, email y redes sociales en una sola bandeja. Vendedores IA que cierran ventas 24/7 y se integran con tus herramientas favoritas.');
$ctaPLab   = $g('cta_primary_label', 'Empezar gratis · 14 dias');
$ctaPUrl   = $g('cta_primary_url', '/register');
$ctaSLab   = $g('cta_secondary_label', 'Solicitar demo');
$ctaSUrl   = $g('cta_secondary_url', '#');
$showPricing = !empty($branding['show_pricing']);
$showChangelog = !empty($branding['show_changelog']);
?>

<!-- ========================== NAV ========================== -->
<nav id="mainNav" x-data="{ open: false }" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="flex items-center gap-2 group">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/30 group-hover:scale-110 transition" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-lg font-bold text-white tracking-tight"><?= e($brand) ?></span>
        </a>

        <div class="hidden lg:flex items-center gap-1 text-sm">
            <a href="#producto"     class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Producto</a>
            <a href="#integraciones" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Integraciones</a>
            <a href="#solucion"      class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Por que ganar</a>
            <a href="#como"          class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Como funciona</a>
            <?php if ($showPricing): ?><a href="#precios" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Precios</a><?php endif; ?>
            <?php if ($showChangelog): ?><a href="<?= url('/changelog') ?>" class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Novedades</a><?php endif; ?>
        </div>

        <div class="hidden md:flex items-center gap-2">
            <a href="<?= url('/login') ?>" class="text-sm text-slate-300 hover:text-white px-4 py-2 transition">Iniciar sesion</a>
            <a href="<?= url($ctaPUrl) ?>" class="text-sm font-semibold text-white px-4 py-2 rounded-lg shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">
                <?= e($ctaPLab) ?>
            </a>
        </div>

        <button @click="open = !open" class="lg:hidden text-white p-2 -mr-2">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div x-show="open" x-transition x-cloak class="lg:hidden border-t border-white/5 bg-[#050817]/95 backdrop-blur-xl">
        <div class="px-6 py-4 space-y-1">
            <a href="#producto"     class="block py-2 text-slate-300">Producto</a>
            <a href="#integraciones" class="block py-2 text-slate-300">Integraciones</a>
            <a href="#solucion"      class="block py-2 text-slate-300">Por que ganar</a>
            <a href="#como"          class="block py-2 text-slate-300">Como funciona</a>
            <?php if ($showPricing): ?><a href="#precios" class="block py-2 text-slate-300">Precios</a><?php endif; ?>
            <?php if ($showChangelog): ?><a href="<?= url('/changelog') ?>" class="block py-2 text-slate-300">Novedades</a><?php endif; ?>
            <div class="pt-3 border-t border-white/5 mt-3 flex gap-2">
                <a href="<?= url('/login') ?>" class="flex-1 px-3 py-2 rounded-lg text-center text-sm bg-white/5 text-white">Iniciar sesion</a>
                <a href="<?= url($ctaPUrl) ?>" class="flex-1 px-3 py-2 rounded-lg text-center text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);"><?= e($ctaPLab) ?></a>
            </div>
        </div>
    </div>
</nav>

<!-- ========================== HERO ========================== -->
<section class="relative pt-32 pb-12 lg:pt-40 lg:pb-16 overflow-hidden">
    <div class="absolute inset-0 bg-mesh"></div>
    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-60"></div>
    <div class="absolute top-20 -left-20 w-[600px] h-[600px] bg-violet-600 rounded-full blur-[120px] opacity-30 animate-float"></div>
    <div class="absolute bottom-0 -right-20 w-[600px] h-[600px] bg-cyan-500 rounded-full blur-[120px] opacity-25 animate-float" style="animation-delay: -3s"></div>

    <div class="relative max-w-7xl mx-auto px-6">
        <div class="grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-6">
                <div class="reveal inline-flex items-center gap-2 px-3 py-1.5 mb-6 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
                    <span class="live-dot"></span>
                    <span class="text-xs font-medium text-slate-300"><?= e($eyebrow) ?></span>
                </div>

                <h1 class="reveal text-4xl md:text-5xl lg:text-6xl font-black text-white leading-[1.05] tracking-tight mb-5 text-balance">
                    <?= nl2br(e($headline)) ?>
                </h1>

                <p class="reveal reveal-delay-1 text-lg text-slate-400 leading-relaxed max-w-xl mb-7">
                    <?= e($heroSub) ?>
                </p>

                <div class="reveal reveal-delay-2 flex flex-col sm:flex-row items-start gap-3 mb-7">
                    <a href="<?= url($ctaPUrl) ?>" class="btn btn-glow btn-lg shadow-xl shadow-violet-500/30">
                        <?= e($ctaPLab) ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="<?= e($ctaSUrl) ?>" target="_blank" class="btn btn-secondary btn-lg">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                        <?= e($ctaSLab) ?>
                    </a>
                </div>

                <div class="reveal reveal-delay-3 flex items-center gap-5 text-xs text-slate-500 flex-wrap">
                    <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Sin tarjeta</div>
                    <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Listo en 5 min</div>
                    <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Multi-numero WhatsApp</div>
                    <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Cancela cuando quieras</div>
                </div>
            </div>

            <!-- Hero mockup: multi-channel unified inbox -->
            <div class="lg:col-span-6 reveal reveal-delay-3">
                <div class="relative">
                    <div class="absolute -inset-6 bg-gradient-to-br from-violet-600 via-fuchsia-500 to-cyan-500 rounded-[2rem] blur-3xl opacity-25"></div>
                    <div class="relative rounded-2xl overflow-hidden border border-white/10 bg-[#0A0F25] shadow-2xl">
                        <div class="px-4 py-3 border-b border-white/5 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500/70"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500/70"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/70"></span>
                            <div class="ml-3 flex-1 max-w-md mx-auto">
                                <div class="px-3 py-1 rounded-md bg-white/5 text-[11px] text-slate-400 font-mono text-center">app.<?= e(strtolower(str_replace(' ', '', $brand))) ?>.com/inbox</div>
                            </div>
                        </div>

                        <!-- Channel switcher -->
                        <div class="px-3 py-2 border-b border-white/5 flex items-center gap-1.5 overflow-x-auto">
                            <span class="text-[9px] uppercase tracking-wider text-slate-500 font-bold">Numeros:</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium" style="background:rgba(16,185,129,.18); color:#10B981; border:1px solid rgba(16,185,129,.4);">● Ventas DO · +18095555000</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] text-slate-400" style="background:rgba(255,255,255,.05);">Soporte MX · +52</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] text-slate-400" style="background:rgba(255,255,255,.05);">VIP US · +1</span>
                        </div>

                        <div class="grid grid-cols-12 min-h-[440px]">
                            <!-- Lista conversaciones -->
                            <div class="col-span-5 bg-[#0F1530] p-3 space-y-1.5 border-r border-white/5">
                                <div class="text-[10px] text-slate-500 uppercase tracking-wider px-2 mb-1">Bandeja unificada</div>
                                <?php $convs = [
                                    ['M', 'Maria Lopez',   'Quiero el plan Pro',  '#10B981', 'WA · DO',  true,  '🟢'],
                                    ['🤖','IA Vendedor',   'Cierre $1,500',       '#7C3AED', 'WA · MX',  false, '⚡'],
                                    ['J', 'Juan Perez',    'Necesito factura',    '#06B6D4', 'WA · US',  false, ''],
                                    ['L', 'Laura Tex',     'Gracias por la demo', '#F59E0B', 'EMAIL',    false, ''],
                                    ['I', 'Pedro Gomez',   'Vi su tienda Insta',  '#EC4899', 'INSTAGRAM',false, ''],
                                    ['🤖','IA Soporte',    'Ticket creado #142',  '#A78BFA', 'WA · DO',  false, ''],
                                ]; foreach ($convs as [$av, $name, $msg, $clr, $chan, $active, $ind]): ?>
                                <div class="flex items-center gap-2 px-2 py-2 rounded-lg <?= $active ? 'bg-violet-500/15 ring-1 ring-violet-500/30' : '' ?>">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0" style="background: <?= $clr ?>;"><?= $av ?></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[11px] text-white font-medium truncate flex items-center gap-1"><?= e($name) ?> <?php if ($ind): ?><span><?= $ind ?></span><?php endif; ?></div>
                                        <div class="text-[10px] text-slate-500 truncate"><?= e($msg) ?></div>
                                    </div>
                                    <span class="text-[8px] font-bold uppercase px-1 rounded" style="background:<?= $clr ?>22; color:<?= $clr ?>;"><?= e($chan) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Chat -->
                            <div class="col-span-7 p-4 flex flex-col">
                                <div class="flex items-center justify-between pb-3 border-b border-white/5 mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center font-bold text-white">M</div>
                                        <div>
                                            <div class="text-sm font-bold text-white">Maria Lopez</div>
                                            <div class="text-[10px] text-emerald-400 flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                En linea · respondiendo desde <span class="font-semibold">Ventas DO</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-[10px] px-2 py-1 rounded bg-violet-500/15 text-violet-300 font-semibold">🤖 Auto-pilot ON</div>
                                </div>
                                <div class="flex-1 space-y-2 overflow-hidden">
                                    <div class="flex justify-start">
                                        <div class="max-w-[75%] px-3 py-1.5 rounded-2xl rounded-bl-sm bg-white/5 text-slate-200 text-xs">Hola, vi su anuncio. Cuanto cuesta el plan Pro?</div>
                                    </div>
                                    <div class="flex justify-end">
                                        <div class="max-w-[75%] px-3 py-1.5 rounded-2xl rounded-br-sm text-white text-xs" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                                            <div class="text-[9px] opacity-80 mb-0.5">⚡ IA Vendedor</div>
                                            Hola Maria! El plan Pro esta en <strong>$1,500/mes</strong>. Incluye agentes IA, multi-numero y reportes. Te lo activo hoy?
                                        </div>
                                    </div>
                                    <div class="flex justify-start">
                                        <div class="max-w-[75%] px-3 py-1.5 rounded-2xl rounded-bl-sm bg-white/5 text-slate-200 text-xs">Si, activamelo. Te paso los datos.</div>
                                    </div>
                                    <div class="flex justify-center mt-3">
                                        <div class="px-3 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-[10px] font-semibold flex items-center gap-1.5">
                                            <span>✓</span> Venta cerrada por IA · <strong>$1,500</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating cards -->
                    <div class="hidden md:flex absolute -left-6 top-1/3 p-3 rounded-xl items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500 flex items-center justify-center text-white">✓</div>
                        <div>
                            <div class="text-xs font-semibold text-white">Lead ganado</div>
                            <div class="text-[10px] text-slate-400">$2,400 · ahora</div>
                        </div>
                    </div>
                    <div class="hidden md:flex absolute -right-6 top-2/3 p-3 rounded-xl items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10" style="animation-delay: -2s">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">🤖</div>
                        <div>
                            <div class="text-xs font-semibold text-white">3 numeros activos</div>
                            <div class="text-[10px] text-slate-400">DO · MX · US</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================== TRUSTED BY ========================== -->
<section class="relative py-10 border-y border-white/5 bg-white/[0.02]">
    <div class="max-w-6xl mx-auto px-6">
        <p class="text-center text-[10px] uppercase tracking-[0.2em] text-slate-500 mb-6">Operando en LATAM, US y Europa</p>
        <div class="flex items-center justify-around gap-6 flex-wrap opacity-70">
            <?php foreach ([
                'CONTACT CENTER PRO', 'BPO LATAM', 'NEXTGEN BPO', 'EMERGE GROUP', 'AGENT360', 'DIGITAL SALES'
            ] as $logo): ?>
            <div class="text-slate-500 font-bold tracking-widest text-sm"><?= e($logo) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== STATS ========================== -->
<section class="relative py-12 border-b border-white/5">
    <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
        <?php foreach ([
            ['10M+',  'mensajes procesados',   '10000000'],
            ['98%',   'tasa de respuesta',     '98',  'suffix' => '%'],
            ['3.5x',  'aumento conversion',    '3.5', 'decimals' => 1, 'suffix' => 'x'],
            ['<1s',   'respuesta IA promedio', '1',   'suffix' => 's', 'prefix' => '<'],
        ] as $stat): ?>
        <div class="reveal">
            <div class="text-4xl md:text-5xl font-black gradient-text-aurora mb-1">
                <?= !empty($stat['prefix']) ? $stat['prefix'] : '' ?><span data-count="<?= $stat[2] ?>" data-decimals="<?= $stat['decimals'] ?? 0 ?>" data-suffix="<?= $stat['suffix'] ?? '' ?>">0</span>
            </div>
            <p class="text-sm text-slate-400"><?= e($stat[1]) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ========================== PRODUCTO PILLARS ========================== -->
<section id="producto" class="relative py-20 lg:py-28">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-violet-500/10 border border-violet-500/20">
                <span class="text-xs font-semibold text-violet-300">PRODUCTO</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Una plataforma. Cinco productos. Cero costuras.</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">Reemplaza Zendesk, HubSpot, Manychat y tu PBX por una sola interfaz que tu equipo dominara en horas.</p>
        </div>

        <!-- Tabs producto -->
        <div x-data="{ tab: 'inbox' }" class="surface p-2 rounded-3xl">
            <div class="flex items-center gap-1 overflow-x-auto p-1.5 mb-3 rounded-2xl bg-white/[0.03]">
                <?php $pillars = [
                    'inbox'    => ['Bandeja Omnicanal', '💬'],
                    'ai'       => ['Equipo IA', '🤖'],
                    'crm'      => ['CRM + Pipeline', '👥'],
                    'campaigns'=> ['Campanas masivas', '📣'],
                    'reports'  => ['Reportes en vivo', '📈'],
                ]; foreach ($pillars as $key => [$lbl, $emoji]): ?>
                <button type="button" @click="tab = '<?= $key ?>'" :class="tab === '<?= $key ?>' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'" class="px-4 py-2 rounded-xl text-sm font-semibold transition flex items-center gap-1.5 whitespace-nowrap">
                    <span><?= $emoji ?></span> <?= e($lbl) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab inbox -->
            <div x-show="tab === 'inbox'" x-transition class="grid lg:grid-cols-2 gap-8 p-6 lg:p-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-violet-300 mb-3">Bandeja unificada</div>
                    <h3 class="text-3xl font-bold text-white mb-4">Todos los canales. Todos los numeros. Una sola pantalla.</h3>
                    <p class="text-slate-400 leading-relaxed mb-5">
                        Conecta multiples numeros de WhatsApp (Cloud API oficial de Meta, Wasapi, Twilio, 360dialog), Instagram DM, Messenger, Telegram, email y webchat. Todo en una sola bandeja con asignacion inteligente, etiquetas, notas internas y plantillas.
                    </p>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <?php foreach ([
                            'WhatsApp Cloud API oficial + Wasapi + Twilio en paralelo',
                            'Multiples numeros operando simultaneamente',
                            'Notas internas, asignaciones y transferencias entre agentes',
                            'Plantillas WhatsApp aprobadas por Meta sincronizadas',
                            'Quick replies y firmas por agente',
                        ] as $bullet): ?>
                        <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><?= e($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#0A0F25] p-5 relative">
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <?php foreach ([
                            ['+1809', 'Ventas DO', '#10B981'],
                            ['+5255', 'Soporte MX', '#06B6D4'],
                            ['+1305', 'VIP US',   '#F59E0B'],
                        ] as [$pf, $lbl, $clr]): ?>
                        <div class="rounded-lg p-2 border" style="background: <?= $clr ?>15; border-color: <?= $clr ?>40;">
                            <div class="text-[10px] font-mono text-slate-400"><?= $pf ?></div>
                            <div class="text-xs font-bold text-white"><?= $lbl ?></div>
                            <div class="text-[10px] mt-1" style="color: <?= $clr ?>;">● Activo</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-white/5">
                            <div class="w-7 h-7 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px] font-bold">M</div>
                            <div class="flex-1 text-xs text-white">Maria Lopez</div>
                            <span class="text-[9px] px-1.5 rounded" style="background:#10B98122; color:#10B981;">DO</span>
                        </div>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-white/5">
                            <div class="w-7 h-7 rounded-full bg-cyan-500 flex items-center justify-center text-white text-[10px] font-bold">J</div>
                            <div class="flex-1 text-xs text-white">Juan Perez</div>
                            <span class="text-[9px] px-1.5 rounded" style="background:#06B6D422; color:#06B6D4;">MX</span>
                        </div>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-white/5">
                            <div class="w-7 h-7 rounded-full bg-amber-500 flex items-center justify-center text-white text-[10px] font-bold">T</div>
                            <div class="flex-1 text-xs text-white">Tom Smith</div>
                            <span class="text-[9px] px-1.5 rounded" style="background:#F59E0B22; color:#F59E0B;">US</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab AI -->
            <div x-show="tab === 'ai'" x-transition x-cloak class="grid lg:grid-cols-2 gap-8 p-6 lg:p-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-violet-300 mb-3">Equipo IA</div>
                    <h3 class="text-3xl font-bold text-white mb-4">Vendedores, soporte y agendadores que nunca duermen.</h3>
                    <p class="text-slate-400 leading-relaxed mb-5">
                        Crea agentes IA especializados (ventas, soporte, agenda, cobranzas) con su propio tono, instrucciones y horario. Se enrutan solos por palabras clave, escalan a humano cuando el cliente lo pide y aprenden de tu base de conocimiento.
                    </p>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <?php foreach ([
                            'Multi-modelo: Claude Opus/Sonnet/Haiku, GPT-4o, Gemini',
                            'Auto-pilot por conversacion: la IA contesta sola',
                            'Handoff inteligente: detecta cuando hace falta un humano',
                            'Toolset: agendar citas, crear tickets, enviar links de pago, etiquetar',
                            'Logs y costos por agente IA',
                        ] as $bullet): ?>
                        <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><?= e($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#0A0F25] p-5 space-y-3">
                    <?php foreach ([
                        ['🤖 Vendedor Pro', 'Claude Opus 4.7', 'Cierra ventas, ofrece catalogo', '#7C3AED', 'Activo · 24/7'],
                        ['🛟 Soporte L1', 'Claude Sonnet 4.6', 'FAQ, tickets, devoluciones', '#06B6D4', 'Activo · 9-18h'],
                        ['📅 Agendador', 'GPT-4o', 'Citas en Google Calendar',   '#10B981', 'Activo · 24/7'],
                    ] as [$nm, $model, $purpose, $clr, $st]): ?>
                    <div class="rounded-xl p-3 border border-white/5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background: <?= $clr ?>22; box-shadow: 0 0 24px <?= $clr ?>22 inset;"><?= mb_substr($nm, 0, 1) ?></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-white"><?= e($nm) ?></div>
                            <div class="text-[10px] text-slate-400"><?= e($purpose) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] font-mono text-slate-500"><?= e($model) ?></div>
                            <div class="text-[10px] font-semibold" style="color:<?= $clr ?>;"><?= e($st) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab CRM -->
            <div x-show="tab === 'crm'" x-transition x-cloak class="grid lg:grid-cols-2 gap-8 p-6 lg:p-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-violet-300 mb-3">CRM + Pipeline</div>
                    <h3 class="text-3xl font-bold text-white mb-4">Cada conversacion alimenta tu pipeline.</h3>
                    <p class="text-slate-400 leading-relaxed mb-5">
                        La IA califica leads, los etiqueta, los mueve por etapas y avisa al vendedor adecuado. Vista Kanban, segmentacion fina y custom fields ilimitados.
                    </p>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <?php foreach ([
                            'Pipeline visual con etapas personalizadas',
                            'Lead scoring automatico (0-100)',
                            'Custom fields, etiquetas y lifecycle stages',
                            'Tareas y recordatorios por contacto',
                            'Importacion CSV / sincronizacion HubSpot, Salesforce, Pipedrive',
                        ] as $bullet): ?>
                        <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><?= e($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#0A0F25] p-4">
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <?php foreach ([
                            ['Nuevos',  '24', '#06B6D4'],
                            ['En contacto', '12', '#3B82F6'],
                            ['Cotizado', '8', '#7C3AED'],
                            ['Ganado',   '5', '#10B981'],
                        ] as [$lbl, $val, $clr]): ?>
                        <div class="rounded-xl p-3 border border-white/5">
                            <div class="text-[9px] font-bold uppercase tracking-wider" style="color:<?= $clr ?>;"><?= $lbl ?></div>
                            <div class="text-2xl font-bold text-white"><?= $val ?></div>
                            <div class="space-y-1 mt-2">
                                <div class="h-1 rounded-full" style="background:<?= $clr ?>33;"></div>
                                <div class="h-1 rounded-full" style="background:<?= $clr ?>22;"></div>
                                <div class="h-1 rounded-full" style="background:<?= $clr ?>11;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tab campaigns -->
            <div x-show="tab === 'campaigns'" x-transition x-cloak class="grid lg:grid-cols-2 gap-8 p-6 lg:p-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-violet-300 mb-3">Campanas masivas</div>
                    <h3 class="text-3xl font-bold text-white mb-4">Manda 10,000 mensajes con un clic.</h3>
                    <p class="text-slate-400 leading-relaxed mb-5">
                        Plantillas WhatsApp aprobadas, segmentacion por etiquetas y custom fields, programacion, A/B testing y reporte en vivo de entrega/lectura/respuesta.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#0A0F25] p-5">
                    <div class="text-xs text-slate-500 mb-2">Campana "Black Friday 50% OFF"</div>
                    <div class="text-2xl font-bold text-white mb-1">8,420 / 10,000 enviados</div>
                    <div class="h-2 rounded-full bg-white/5 overflow-hidden mb-4">
                        <div class="h-full rounded-full" style="width: 84%; background: linear-gradient(90deg,#7C3AED,#06B6D4);"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-white/5 p-2"><div class="text-xs text-slate-400">Entregados</div><div class="text-lg font-bold text-white">8,142</div></div>
                        <div class="rounded-lg bg-white/5 p-2"><div class="text-xs text-slate-400">Leidos</div><div class="text-lg font-bold text-emerald-400">6,890</div></div>
                        <div class="rounded-lg bg-white/5 p-2"><div class="text-xs text-slate-400">Respuestas</div><div class="text-lg font-bold text-cyan-400">1,124</div></div>
                    </div>
                </div>
            </div>

            <!-- Tab reports -->
            <div x-show="tab === 'reports'" x-transition x-cloak class="grid lg:grid-cols-2 gap-8 p-6 lg:p-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-violet-300 mb-3">Reportes en vivo</div>
                    <h3 class="text-3xl font-bold text-white mb-4">Datos hoy, decisiones manana.</h3>
                    <p class="text-slate-400 leading-relaxed mb-5">
                        SLA, AHT, tasa de resolucion, conversion por canal, ranking de agentes y embudos completos. Todo en tiempo real.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#0A0F25] p-5">
                    <svg viewBox="0 0 280 100" class="w-full h-24">
                        <defs>
                            <linearGradient id="grad-rep" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.7"/>
                                <stop offset="100%" stop-color="#7C3AED" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M0,80 L20,70 L40,75 L60,55 L80,60 L100,40 L120,48 L140,30 L160,38 L180,22 L200,28 L220,12 L240,18 L260,8 L280,12 L280,100 L0,100 Z" fill="url(#grad-rep)"/>
                        <path d="M0,80 L20,70 L40,75 L60,55 L80,60 L100,40 L120,48 L140,30 L160,38 L180,22 L200,28 L220,12 L240,18 L260,8 L280,12" fill="none" stroke="#7C3AED" stroke-width="2"/>
                    </svg>
                    <div class="grid grid-cols-3 gap-2 text-center mt-3">
                        <div><div class="text-[10px] text-slate-500">AHT</div><div class="text-lg font-bold text-white">2:14</div></div>
                        <div><div class="text-[10px] text-slate-500">CSAT</div><div class="text-lg font-bold text-emerald-400">94%</div></div>
                        <div><div class="text-[10px] text-slate-500">FCR</div><div class="text-lg font-bold text-cyan-400">86%</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================== INTEGRATIONS ========================== -->
<section id="integraciones" class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-cyan-500/10 border border-cyan-500/20">
                <span class="text-xs font-semibold text-cyan-300">+30 INTEGRACIONES NATIVAS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Se conecta con todo lo que ya usas</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">WhatsApp Cloud API, CRMs, contact center, ecommerce, pagos y miles mas via Zapier/Make.</p>
        </div>

        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3 mb-8">
            <?php
            $integrations = [
                ['WhatsApp Cloud', '#25D366', 'WA'],
                ['Wasapi',         '#10B981', 'WS'],
                ['Twilio',         '#F22F46', 'TW'],
                ['360dialog',      '#0EA5E9', '36'],
                ['Telegram',       '#0088CC', 'TG'],
                ['Messenger',      '#0084FF', 'MS'],
                ['Instagram',      '#E1306C', 'IG'],
                ['HubSpot',        '#FF7A59', 'HS'],
                ['Salesforce',     '#00A1E0', 'SF'],
                ['Pipedrive',      '#1A1A1A', 'PD'],
                ['Zoho CRM',       '#E42527', 'ZH'],
                ['Slack',          '#4A154B', 'SL'],
                ['MS Teams',       '#5059C9', 'MT'],
                ['Discord',        '#5865F2', 'DC'],
                ['Stripe',         '#635BFF', 'ST'],
                ['MercadoPago',    '#00B1EA', 'MP'],
                ['PayPal',         '#003087', 'PP'],
                ['Shopify',        '#7AB55C', 'SH'],
                ['WooCommerce',    '#96588A', 'WC'],
                ['Google Calendar','#4285F4', 'GC'],
                ['Google Sheets',  '#0F9D58', 'GS'],
                ['Resend',         '#0F1530', 'RS'],
                ['SendGrid',       '#1A82E2', 'SG'],
                ['Mailgun',        '#FE3F4D', 'MG'],
                ['Zapier',         '#FF4A00', 'ZP'],
                ['Make',           '#6D00CC', 'MK'],
                ['n8n',            '#EA4B71', 'N8'],
                ['Webhooks',       '#94A3B8', 'WH'],
                ['Genesys',        '#F36F21', 'GN'],
                ['Aircall',        '#00B388', 'AC'],
            ];
            foreach ($integrations as [$name, $color, $abbr]): ?>
            <div class="reveal rounded-xl p-3 bg-white/[0.03] border border-white/10 hover:bg-white/[0.06] hover:border-white/20 transition flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background: <?= $color ?>;"><?= $abbr ?></div>
                <div class="text-xs font-semibold text-slate-200 truncate"><?= e($name) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center">
            <a href="<?= url('/register') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold inline-flex items-center gap-1">
                Ver catalogo completo de integraciones
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <p class="mt-3 text-xs text-slate-500">Las integraciones premium estan disponibles en el plan Enterprise.</p>
        </div>
    </div>
</section>

<!-- ========================== POR QUE GANAR ========================== -->
<section id="solucion" class="relative py-20 lg:py-28">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                <span class="text-xs font-semibold text-emerald-300">POR QUE NOSOTROS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Construido para contact centers y empresas serias</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">No es otro chatbot. Es la infraestructura completa que ahorra nomina y multiplica conversion.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <?php
            $benefits = [
                ['🚀', 'Multi-numero ilimitado', 'Conecta cuantos numeros necesites: Cloud API oficial, Wasapi, Twilio. Todos en una sola bandeja.', '#7C3AED'],
                ['💰', 'Reduce 60% tu nomina',   'Un equipo de IA equivale a 3-5 representantes humanos. Sin renuncias, sin vacaciones.', '#10B981'],
                ['⚡', 'Setup en 5 minutos',     'Conecta tu WhatsApp, define tus agentes IA y empieza a vender. Sin instalacion, sin servidor.', '#06B6D4'],
                ['🎯', 'Asignacion inteligente', 'Por idioma, horario, area, score. La IA enruta cada conversacion al agente correcto.', '#A78BFA'],
                ['📊', 'Reportes ejecutivos',    'AHT, CSAT, FCR, SLA. Conversion por canal, agente y campana. Datos que ejecutan.', '#F59E0B'],
                ['🛡', 'Tu data es tuya',        'Multi-tenant aislado, audit logs, GDPR-ready, encriptacion en reposo. Cumple ISO 27001.', '#F43F5E'],
                ['🌍', 'LATAM-first',            'Soporte en espanol, integraciones con MercadoPago, Wasapi y proveedores locales.', '#0EA5E9'],
                ['🔌', 'API y Webhooks',         'API REST publica + webhooks salientes. Integra todo lo demas con Zapier o Make.', '#22C55E'],
                ['👥', 'Soporte humano',         'Customer Success dedicado en plan Business. Onboarding 1-on-1 en Enterprise.', '#EC4899'],
            ];
            foreach ($benefits as $i => [$icon, $title, $desc, $clr]):
            ?>
            <div class="reveal spotlight-card relative rounded-2xl p-6 bg-white/[0.03] border border-white/10 hover:border-white/20 transition" style="animation-delay: <?= $i * 0.04 ?>s">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-4" style="background: <?= $clr ?>20; box-shadow: 0 0 30px <?= $clr ?>30 inset;"><?= $icon ?></div>
                <h3 class="font-bold text-white text-lg mb-2"><?= e($title) ?></h3>
                <p class="text-sm text-slate-400 leading-relaxed"><?= e($desc) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== HOW IT WORKS ========================== -->
<section id="como" class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-5xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-amber-500/10 border border-amber-500/20">
                <span class="text-xs font-semibold text-amber-300">EN MINUTOS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">De cero a vender en 4 pasos</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <?php
            $steps = [
                ['1', 'Conecta tus numeros',      'Pega tu API key o Cloud API. Operas tantos numeros como necesites.'],
                ['2', 'Carga catalogo + FAQ',     'Productos, precios y respuestas frecuentes. Nutrimos a la IA.'],
                ['3', 'Crea tus agentes IA',      'Vendedor, soporte, agendador. Define tono, reglas, horario y handoff.'],
                ['4', 'Activa y mide',            'La IA atiende, vende, agenda y escala a humanos cuando hace falta.'],
            ];
            foreach ($steps as $i => [$n, $title, $desc]):
            ?>
            <div class="reveal flex items-start gap-4 p-5 rounded-2xl bg-white/[0.03] border border-white/10" style="animation-delay: <?= $i * 0.07 ?>s">
                <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center text-xl font-black text-white shadow-lg shadow-violet-500/30" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"><?= $n ?></div>
                <div>
                    <h3 class="font-bold text-white mb-1"><?= e($title) ?></h3>
                    <p class="text-sm text-slate-400"><?= e($desc) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== TESTIMONIALS ========================== -->
<section class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <h2 class="reveal heading-lg text-white mb-4">Lo que dicen nuestros clientes</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <?php foreach ([
                ['Sofia Vargas', 'CEO · BPO Latam',     '“Pasamos de 4 herramientas a una. Recortamos 30% el tiempo de respuesta y 22% el costo de operacion.”', 'SV', '#7C3AED'],
                ['Diego Pena',   'Operations · Agent360', '“La IA cierra el 40% de las ventas sola. El equipo humano solo entra cuando es urgente.”', 'DP', '#06B6D4'],
                ['Carla Rivera', 'CX Manager · NextGen', '“Operamos 8 numeros desde una sola pantalla. Game changer.”', 'CR', '#10B981'],
            ] as $i => [$name, $role, $quote, $av, $clr]): ?>
            <div class="reveal rounded-2xl p-6 bg-white/[0.03] border border-white/10" style="animation-delay: <?= $i * 0.06 ?>s">
                <div class="flex items-center gap-1 mb-3">
                    <?php for ($j=0; $j<5; $j++): ?>
                    <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    <?php endfor; ?>
                </div>
                <p class="text-slate-300 text-sm mb-5 leading-relaxed">"<?= e(substr($quote, 1, -1)) ?>"</p>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs" style="background: <?= $clr ?>;"><?= $av ?></div>
                    <div>
                        <div class="font-semibold text-white text-sm"><?= e($name) ?></div>
                        <div class="text-[10px] text-slate-500"><?= e($role) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== CHANGELOG TEASER ========================== -->
<?php if ($showChangelog && !empty($changelog)): ?>
<section class="relative py-16 border-t border-white/5">
    <div class="max-w-5xl mx-auto px-6">
        <div class="flex items-end justify-between mb-6 flex-wrap gap-3">
            <div>
                <div class="text-xs font-semibold text-cyan-300 uppercase tracking-wider mb-1">Mejoramos cada semana</div>
                <h3 class="text-2xl font-bold text-white">Lo ultimo del producto</h3>
            </div>
            <a href="<?= url('/changelog') ?>" class="text-sm text-cyan-400 hover:underline">Ver changelog completo →</a>
        </div>
        <div class="grid md:grid-cols-3 gap-3">
            <?php foreach (array_slice($changelog, 0, 3) as $entry):
                $cat = (string) ($entry['category'] ?? 'feature');
                [$catLabel, $catColor] = (\App\Models\Changelog::CATEGORIES)[$cat] ?? ['Otro', '#A78BFA'];
            ?>
            <a href="<?= url('/changelog') ?>" class="reveal block rounded-2xl p-5 bg-white/[0.03] border border-white/10 hover:bg-white/[0.06] transition">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded" style="background: <?= $catColor ?>22; color: <?= $catColor ?>;"><?= e($catLabel) ?></span>
                    <?php if (!empty($entry['version'])): ?>
                    <span class="text-[10px] font-mono text-slate-500">v<?= e((string) $entry['version']) ?></span>
                    <?php endif; ?>
                </div>
                <h4 class="font-semibold text-white mb-1"><?= e((string) $entry['title']) ?></h4>
                <?php if (!empty($entry['summary'])): ?>
                <p class="text-xs text-slate-400 line-clamp-2"><?= e((string) $entry['summary']) ?></p>
                <?php endif; ?>
                <div class="text-[10px] text-slate-500 mt-3"><?= !empty($entry['published_at']) ? date('d M Y', strtotime((string) $entry['published_at'])) : '' ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================== PRICING ========================== -->
<?php if ($showPricing && !empty($plans)): ?>
<section id="precios" class="relative py-20 lg:py-28 border-t border-white/5" x-data="{ yearly: false }">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-amber-500/10 border border-amber-500/20">
                <span class="text-xs font-semibold text-amber-300">PRECIOS TRANSPARENTES</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4">Elige tu plan</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 mb-7">Sin sorpresas. Cancela cuando quieras.</p>

            <div class="reveal reveal-delay-2 inline-flex items-center gap-1 p-1 rounded-full border border-white/10 bg-white/5">
                <button @click="yearly = false" :class="!yearly ? 'bg-white text-slate-900' : 'text-slate-300'" class="px-4 py-1.5 rounded-full text-xs font-semibold transition">Mensual</button>
                <button @click="yearly = true" :class="yearly ? 'bg-white text-slate-900' : 'text-slate-300'" class="px-4 py-1.5 rounded-full text-xs font-semibold transition flex items-center gap-1.5">
                    Anual <span class="px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[9px]">−20%</span>
                </button>
            </div>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-<?= min(count($plans), 4) ?> gap-4 max-w-7xl mx-auto">
            <?php foreach ($plans as $i => $plan):
                $isPopular = $plan['slug'] === 'business' || ($plan['slug'] === 'professional' && count($plans) <= 3);
                $isEnterprise = $plan['slug'] === 'enterprise';
            ?>
            <div class="reveal relative rounded-2xl p-6 <?= $isPopular ? 'bg-gradient-to-br from-violet-500/12 to-cyan-500/12 border-violet-500/40' : ($isEnterprise ? 'bg-gradient-to-br from-amber-500/8 to-rose-500/8 border-amber-500/30' : 'bg-white/[0.03] border-white/10') ?> border" style="animation-delay: <?= $i * 0.05 ?>s">
                <?php if ($isPopular): ?>
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-white shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">Mas popular</div>
                <?php elseif ($isEnterprise): ?>
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-white shadow-lg" style="background: linear-gradient(135deg,#F59E0B,#EF4444);">Enterprise</div>
                <?php endif; ?>

                <h3 class="text-xl font-bold text-white mb-1"><?= e($plan['name']) ?></h3>
                <p class="text-xs text-slate-400 mb-4 min-h-[32px]"><?= e((string) ($plan['description'] ?? '')) ?></p>

                <div class="mb-5">
                    <span class="text-4xl font-bold text-white">$<span x-text="yearly ? <?= (int) round($plan['price_yearly'] / 12) ?> : <?= (int) $plan['price_monthly'] ?>"><?= number_format((int) $plan['price_monthly']) ?></span></span>
                    <span class="text-sm text-slate-500">/mes</span>
                    <div x-show="yearly" class="text-[11px] text-emerald-400 mt-1">Facturado $<?= number_format((int) $plan['price_yearly']) ?>/ano</div>
                </div>

                <a href="<?= url('/register') ?>" class="block w-full py-2.5 rounded-xl text-center text-sm font-semibold transition <?= $isPopular ? 'text-white shadow-lg shadow-violet-500/30' : ($isEnterprise ? 'text-white shadow-lg' : 'bg-white/5 text-white hover:bg-white/10') ?>"
                   <?= $isPopular ? 'style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"' : ($isEnterprise ? 'style="background: linear-gradient(135deg,#F59E0B,#EF4444);"' : '') ?>>
                    <?= $isEnterprise ? 'Hablar con ventas' : 'Empezar gratis' ?>
                </a>

                <ul class="mt-5 space-y-2 text-sm text-slate-300">
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><strong class="text-white"><?= number_format((int) $plan['max_users']) ?></strong> usuarios</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><strong class="text-white"><?= number_format((int) $plan['max_contacts']) ?></strong> contactos</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><strong class="text-white"><?= number_format((int) $plan['max_messages']) ?></strong> mensajes/mes</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['ai_enabled']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>Agentes IA <?= !empty($plan['ai_enabled']) ? 'incluidos' : '<span class="opacity-60">no incluidos</span>' ?></li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['advanced_reports']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>Reportes avanzados</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['api_access']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>Acceso API + Webhooks</li>
                    <?php if ($plan['slug'] === 'enterprise'): ?>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>HubSpot, Salesforce, Genesys, Five9</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>Customer Success dedicado</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>SLA 99.99% + on-prem opcional</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================== FAQ ========================== -->
<section id="faq" class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-3xl mx-auto px-6">
        <div class="text-center mb-10">
            <h2 class="reveal heading-lg text-white mb-4">Preguntas frecuentes</h2>
        </div>
        <div class="space-y-3">
            <?php
            $faqs = [
                ['¿Necesito instalar algo?',                 'No. Es 100% en la nube. Te registras, conectas tu WhatsApp y empiezas a operar en minutos.'],
                ['¿Soporta WhatsApp Cloud API oficial?',     'Si. Soportamos WhatsApp Business Cloud API directo de Meta, ademas de Wasapi, Twilio y 360dialog. Puedes operar varios numeros en simultaneo.'],
                ['¿Puedo conectar varios numeros?',          'Si. Puedes conectar tantos numeros como tu plan permita y operarlos desde una sola bandeja unificada.'],
                ['¿La IA realmente vende?',                  'Si. Le cargas tu catalogo, instrucciones y politicas. La IA negocia, ofrece, agenda y cierra. Tu validas o transfieres a humano cuando quieras.'],
                ['¿Que integraciones tiene?',                'Mas de 30 nativas: HubSpot, Salesforce, Stripe, Shopify, Slack, Google Calendar, Zapier y mas. Las premium se desbloquean en Enterprise.'],
                ['¿Mis datos son privados?',                 'Si. Cada empresa tiene su data totalmente aislada. No usamos tus conversaciones para entrenar modelos. GDPR-ready.'],
                ['¿Puedo cancelar cuando quiera?',           'Si. Cancelas con un clic, sin contratos largos ni penalidades.'],
                ['¿Hay descuento anual?',                    'Si, 20% al pagar anual. Tambien tenemos descuentos especiales para BPOs y onboarding gratis en Business.'],
            ];
            foreach ($faqs as $i => [$q, $a]):
            ?>
            <details class="reveal group rounded-xl border border-white/10 bg-white/[0.03] overflow-hidden" style="animation-delay: <?= $i * 0.04 ?>s">
                <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between font-semibold text-white">
                    <span><?= e($q) ?></span>
                    <svg class="w-4 h-4 text-slate-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-4 text-sm text-slate-400 leading-relaxed"><?= e($a) ?></div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== CTA FINAL ========================== -->
<section class="relative py-20 border-t border-white/5">
    <div class="absolute inset-0 bg-gradient-to-b from-violet-500/5 to-transparent"></div>
    <div class="relative max-w-4xl mx-auto px-6 text-center">
        <h2 class="reveal heading-lg text-white mb-4 text-balance">
            Empieza hoy. <span class="gradient-text-aurora">Vende mas manana.</span>
        </h2>
        <p class="reveal reveal-delay-1 text-lg text-slate-400 mb-7">
            14 dias gratis. Sin tarjeta. Sin compromisos. Multi-numero. Multi-canal. Multi-IA.
        </p>
        <div class="reveal reveal-delay-2 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= url($ctaPUrl) ?>" class="btn btn-glow btn-lg shadow-xl shadow-violet-500/40">
                <?= e($ctaPLab) ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="<?= e($ctaSUrl) ?>" target="_blank" class="btn btn-secondary btn-lg"><?= e($ctaSLab) ?></a>
        </div>
    </div>
</section>

<!-- ========================== FOOTER ========================== -->
<footer class="border-t border-white/5 pt-12 pb-8">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid md:grid-cols-5 gap-8 pb-10 border-b border-white/5">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-bold text-white"><?= e($brand) ?></span>
                </div>
                <p class="text-sm text-slate-400 max-w-xs"><?= e($tagline) ?></p>
            </div>
            <div>
                <div class="text-xs uppercase font-bold text-slate-500 tracking-widest mb-3">Producto</div>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="#producto" class="hover:text-white transition">Bandeja unificada</a></li>
                    <li><a href="#producto" class="hover:text-white transition">Equipo IA</a></li>
                    <li><a href="#integraciones" class="hover:text-white transition">Integraciones</a></li>
                    <li><a href="#precios" class="hover:text-white transition">Precios</a></li>
                </ul>
            </div>
            <div>
                <div class="text-xs uppercase font-bold text-slate-500 tracking-widest mb-3">Empresa</div>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="<?= url('/changelog') ?>" class="hover:text-white transition">Changelog</a></li>
                    <li><a href="#" class="hover:text-white transition">Blog</a></li>
                    <li><a href="#" class="hover:text-white transition">Carreras</a></li>
                    <li><a href="<?= e($ctaSUrl) ?>" class="hover:text-white transition">Contacto</a></li>
                </ul>
            </div>
            <div>
                <div class="text-xs uppercase font-bold text-slate-500 tracking-widest mb-3">Legal</div>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="#" class="hover:text-white transition">Privacidad</a></li>
                    <li><a href="#" class="hover:text-white transition">Terminos</a></li>
                    <li><a href="#" class="hover:text-white transition">DPA</a></li>
                    <li><a href="#" class="hover:text-white transition">Estado del servicio</a></li>
                </ul>
            </div>
        </div>

        <div class="pt-6 flex flex-wrap items-center justify-between gap-4 text-xs text-slate-500">
            <div>© <?= date('Y') ?> <?= e($g('legal_company', $brand)) ?>. Todos los derechos reservados.</div>
            <div class="flex items-center gap-4">
                <?php if ($email = $g('contact_email')): ?><a href="mailto:<?= e($email) ?>" class="hover:text-white"><?= e($email) ?></a><?php endif; ?>
                <a href="<?= url('/login') ?>" class="hover:text-white">Iniciar sesion</a>
            </div>
        </div>
    </div>
</footer>

<?php \App\Core\View::stop(); ?>
