<?php
/** @var array $plans */
/** @var array $changelog */
/** @var array $branding */
\App\Core\View::extend('layouts.landing');
\App\Core\View::start('content');

$g = fn(string $k, string $d = '') => (string) ($branding[$k] ?? $d);

$brand     = $g('brand_name', 'Kyros Pulse');
$tagline   = $g('brand_tagline', 'El sistema operativo de tu negocio digital');
$eyebrow   = $g('hero_eyebrow', 'CRM + WhatsApp + IA en una plataforma');
$headline  = $g('hero_headline', 'Convierte cada conversacion en una venta — sin contratar mas personal');
$heroSub   = $g('hero_sub', 'Vendedores, soporte y agendadores IA que atienden por ti 24/7. CRM, automatizaciones y reportes en un solo panel.');
$ctaPLab   = $g('cta_primary_label', 'Empezar gratis 14 dias');
$ctaPUrl   = $g('cta_primary_url', '/register');
$ctaSLab   = $g('cta_secondary_label', 'Solicitar demo');
$ctaSUrl   = $g('cta_secondary_url', '#');
$showPricing = !empty($branding['show_pricing']);
$showChangelog = !empty($branding['show_changelog']);
?>

<!-- ========================== NAV ========================== -->
<nav id="mainNav" x-data="{ open: false }" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <div class="max-w-6xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="flex items-center gap-2 group">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/30 group-hover:scale-110 transition" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-lg font-bold text-white tracking-tight"><?= e($brand) ?></span>
        </a>

        <div class="hidden lg:flex items-center gap-1 text-sm">
            <a href="#beneficios"  class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Beneficios</a>
            <a href="#modulos"     class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Que incluye</a>
            <a href="#como"        class="px-4 py-2 text-slate-400 hover:text-white transition rounded-lg hover:bg-white/5">Como funciona</a>
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
            <a href="#beneficios" class="block py-2 text-slate-300">Beneficios</a>
            <a href="#modulos"    class="block py-2 text-slate-300">Que incluye</a>
            <a href="#como"       class="block py-2 text-slate-300">Como funciona</a>
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
<section class="relative pt-32 pb-16 lg:pt-40 lg:pb-20 overflow-hidden">
    <div class="absolute inset-0 bg-mesh"></div>
    <div class="absolute inset-0 bg-grid bg-grid-fade opacity-60"></div>
    <div class="absolute top-20 -left-20 w-96 h-96 bg-violet-600 rounded-full blur-[120px] opacity-30 animate-float"></div>
    <div class="absolute bottom-20 -right-20 w-96 h-96 bg-cyan-500 rounded-full blur-[120px] opacity-25 animate-float" style="animation-delay: -3s"></div>

    <div class="relative max-w-5xl mx-auto px-6 text-center">
        <div class="reveal inline-flex items-center gap-2 px-3 py-1.5 mb-7 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
            <span class="live-dot"></span>
            <span class="text-xs font-medium text-slate-300"><?= e($eyebrow) ?></span>
        </div>

        <h1 class="reveal heading-xl text-white text-balance mb-6">
            <?= nl2br(e($headline)) ?>
        </h1>

        <p class="reveal reveal-delay-1 text-lg lg:text-xl text-slate-400 leading-relaxed max-w-2xl mx-auto mb-9 text-balance">
            <?= e($heroSub) ?>
        </p>

        <div class="reveal reveal-delay-2 flex flex-col sm:flex-row items-center justify-center gap-3 mb-8">
            <a href="<?= url($ctaPUrl) ?>" class="btn btn-glow btn-lg shadow-xl shadow-violet-500/30">
                <?= e($ctaPLab) ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="<?= e($ctaSUrl) ?>" target="_blank" class="btn btn-secondary btn-lg">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                <?= e($ctaSLab) ?>
            </a>
        </div>

        <div class="reveal reveal-delay-3 flex items-center justify-center gap-6 text-xs text-slate-500 flex-wrap">
            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Sin tarjeta de credito</div>
            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Listo en 5 minutos</div>
            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Soporte humano</div>
        </div>
    </div>

    <!-- Mockup compacto -->
    <div class="reveal reveal-delay-3 max-w-5xl mx-auto px-6 mt-14">
        <div class="relative">
            <div class="absolute -inset-4 bg-gradient-to-r from-violet-600 to-cyan-500 rounded-3xl blur-3xl opacity-25"></div>
            <div class="relative rounded-2xl overflow-hidden border border-white/10 bg-[#0A0F25] shadow-2xl">
                <div class="px-4 py-3 border-b border-white/5 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/70"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/70"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/70"></span>
                    <div class="ml-3 flex-1 max-w-md mx-auto">
                        <div class="px-3 py-1 rounded-md bg-white/5 text-[11px] text-slate-400 font-mono text-center">app.<?= e(strtolower($brand)) ?>.com/inbox</div>
                    </div>
                </div>
                <div class="p-1 grid grid-cols-12 gap-1 min-h-[420px]">
                    <!-- Lista conversaciones -->
                    <div class="col-span-4 bg-[#0F1530] rounded-l-xl p-3 space-y-1.5">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider px-2 mb-1">Bandeja</div>
                        <?php $convs = [
                            ['M', 'Maria Lopez', 'Quiero el plan Pro', '#10B981', true],
                            ['🤖', 'IA Vendedor', 'Cierre $1,500', '#7C3AED', false],
                            ['J', 'Juan Perez',   'Necesito factura', '#06B6D4', false],
                            ['L', 'Laura Tex',    'Gracias por la demo', '#F59E0B', false],
                            ['🤖', 'IA Soporte',  'Ticket creado #142', '#A78BFA', false],
                        ]; foreach ($convs as [$av, $name, $msg, $clr, $active]): ?>
                        <div class="flex items-center gap-2 px-2 py-2 rounded-lg <?= $active ? 'bg-violet-500/15 ring-1 ring-violet-500/30' : '' ?>">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0" style="background: <?= $clr ?>;"><?= $av ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[11px] text-white font-medium truncate"><?= e($name) ?></div>
                                <div class="text-[10px] text-slate-500 truncate"><?= e($msg) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Chat -->
                    <div class="col-span-8 p-4 flex flex-col">
                        <div class="flex items-center justify-between pb-3 border-b border-white/5 mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center font-bold text-white">M</div>
                                <div>
                                    <div class="text-sm font-bold text-white">Maria Lopez</div>
                                    <div class="text-[10px] text-emerald-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> En linea</div>
                                </div>
                            </div>
                            <div class="text-[10px] px-2 py-1 rounded bg-violet-500/15 text-violet-300 font-semibold">🤖 Auto-pilot ON</div>
                        </div>
                        <div class="flex-1 space-y-2 overflow-hidden">
                            <div class="flex justify-start">
                                <div class="max-w-[70%] px-3 py-1.5 rounded-2xl rounded-bl-sm bg-white/5 text-slate-200 text-xs">Hola, vi tu publicacion. ¿Cuanto cuesta el plan Pro?</div>
                            </div>
                            <div class="flex justify-end">
                                <div class="max-w-[70%] px-3 py-1.5 rounded-2xl rounded-br-sm text-white text-xs" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                                    <div class="text-[9px] opacity-80 mb-0.5">⚡ IA</div>
                                    Hola Maria, el plan Pro esta en <strong>$1,500/mes</strong> e incluye agentes IA, WhatsApp multiagente y reportes. ¿Te lo activo hoy?
                                </div>
                            </div>
                            <div class="flex justify-start">
                                <div class="max-w-[70%] px-3 py-1.5 rounded-2xl rounded-bl-sm bg-white/5 text-slate-200 text-xs">Si, activamelo. Te paso los datos.</div>
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
            <div class="hidden md:flex absolute -left-12 top-1/3 p-3 rounded-xl items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10">
                <div class="w-9 h-9 rounded-lg bg-emerald-500 flex items-center justify-center">✓</div>
                <div>
                    <div class="text-xs font-semibold text-white">Lead ganado</div>
                    <div class="text-[10px] text-slate-400">$2,400 · ahora</div>
                </div>
            </div>
            <div class="hidden md:flex absolute -right-12 top-2/3 p-3 rounded-xl items-center gap-2 animate-float shadow-xl backdrop-blur-xl bg-white/5 border border-white/10" style="animation-delay: -2s">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"><span>🤖</span></div>
                <div>
                    <div class="text-xs font-semibold text-white">IA respondio</div>
                    <div class="text-[10px] text-slate-400">15 mensajes auto</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================== STATS ========================== -->
<section class="relative py-12 border-y border-white/5 bg-white/[0.02]">
    <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
        <?php foreach ([
            ['10M+',  'mensajes procesados',     '10000000'],
            ['98%',   'tasa de respuesta',       '98', 'suffix' => '%'],
            ['3.5x',  'aumento conversion',      '3.5', 'decimals' => 1, 'suffix' => 'x'],
            ['<1s',   'respuesta IA promedio',   '1', 'suffix' => 's', 'prefix' => '<'],
        ] as $stat): ?>
        <div class="reveal">
            <div class="heading-md gradient-text-aurora mb-1">
                <?= !empty($stat['prefix']) ? $stat['prefix'] : '' ?><span data-count="<?= $stat[2] ?>" data-decimals="<?= $stat['decimals'] ?? 0 ?>" data-suffix="<?= $stat['suffix'] ?? '' ?>">0</span>
            </div>
            <p class="text-sm text-slate-400"><?= e($stat[1]) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ========================== BENEFICIOS ========================== -->
<section id="beneficios" class="relative py-20 lg:py-28">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-violet-500/10 border border-violet-500/20">
                <span class="text-xs font-semibold text-violet-300">PORQUE ELEGIRNOS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Resultados, no solo herramientas</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">
                <?= e($brand) ?> reemplaza nomina, atiende 24/7 y nunca olvida hacer seguimiento.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <?php
            $benefits = [
                ['🚀', 'Cierra mas ventas',    'Agentes IA que atienden, ofrecen tu catalogo y cierran ventas mientras duermes. Cada conversacion es una oportunidad capturada.', '#7C3AED'],
                ['💰', 'Reduce nomina',         'Un equipo de IA equivale a 3-5 representantes humanos a una fraccion del costo, sin renuncias ni vacaciones.', '#10B981'],
                ['⚡', 'Atiende en segundos',  'Respuestas instantaneas 24/7. Tus clientes nunca esperan, tu tasa de conversion sube 3x.', '#06B6D4'],
                ['🎯', 'Personaliza por intencion', 'Agentes especializados (ventas, soporte, agenda) que se enrutan solos segun lo que pide el cliente.', '#A78BFA'],
                ['📊', 'Decide con datos',     'Dashboards y reportes en vivo. Ve que campana convierte, que agente cierra mas y donde mejorar.', '#F59E0B'],
                ['🛡', 'Tu data es tuya',      'Multi-tenant aislado, backups, auditoria completa. Tus conversaciones nunca alimentan modelos publicos.', '#F43F5E'],
            ];
            foreach ($benefits as $i => [$icon, $title, $desc, $clr]):
            ?>
            <div class="reveal spotlight-card relative rounded-2xl p-6 bg-white/[0.03] border border-white/10 hover:border-white/20 transition" style="animation-delay: <?= $i * 0.05 ?>s">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-4" style="background: <?= $clr ?>20; box-shadow: 0 0 30px <?= $clr ?>30 inset;"><?= $icon ?></div>
                <h3 class="font-bold text-white text-lg mb-2"><?= e($title) ?></h3>
                <p class="text-sm text-slate-400 leading-relaxed"><?= e($desc) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== MODULOS ========================== -->
<section id="modulos" class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-cyan-500/10 border border-cyan-500/20">
                <span class="text-xs font-semibold text-cyan-300">QUE INCLUYE</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">Todo en una sola plataforma</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400 text-balance">Reemplaza 5 herramientas con <?= e($brand) ?>.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php
            $modules = [
                ['💬', 'WhatsApp multiagente', 'Bandeja unificada con asignacion, etiquetas, notas internas, plantillas y multiples numeros.'],
                ['🤖', 'Equipo IA ilimitado',   'Crea vendedores, soporte y agendadores IA. Se enrutan solos por palabras clave y horario.'],
                ['👥', 'CRM completo',           'Contactos, segmentacion, scoring, lifecycle, custom fields y pipeline visual de ventas.'],
                ['📦', 'Catalogo de productos', 'Tus productos y precios siempre actualizados, la IA los ofrece automaticamente.'],
                ['🎫', 'Tickets y soporte',     'Sistema de tickets con prioridad, SLA y resolucion. Conversa y resuelve en un mismo lugar.'],
                ['⚡', 'Automatizaciones',       'Triggers, condiciones y acciones. "Si llega lead de Facebook, asignar a Maria y enviar bienvenida".'],
                ['📣', 'Campanas masivas',       'Envios programados, plantillas WhatsApp aprobadas, segmentacion fina y reporte en vivo.'],
                ['📅', 'Tareas y agenda',        'Recordatorios, repeticiones, asignacion. La IA puede agendar citas y crear tareas sola.'],
                ['📈', 'Reportes en vivo',       'Dashboards, embudos, conversion, tiempo de respuesta, performance por agente.'],
            ];
            foreach ($modules as $i => [$icon, $title, $desc]):
            ?>
            <div class="reveal rounded-2xl p-5 bg-white/[0.03] border border-white/10 hover:bg-white/[0.06] hover:-translate-y-0.5 transition" style="animation-delay: <?= ($i % 6) * 0.04 ?>s">
                <div class="text-2xl mb-2"><?= $icon ?></div>
                <h3 class="font-semibold text-white mb-1"><?= e($title) ?></h3>
                <p class="text-xs text-slate-400 leading-relaxed"><?= e($desc) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================== COMO FUNCIONA ========================== -->
<section id="como" class="relative py-20 lg:py-28">
    <div class="max-w-5xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                <span class="text-xs font-semibold text-emerald-300">EN MINUTOS</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4 text-balance">De cero a vendiendo en 4 pasos</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <?php
            $steps = [
                ['1', 'Conecta tu WhatsApp',      'Pega tu API key, valida tu numero. Listo.'],
                ['2', 'Carga catalogo y FAQ',     'Productos, precios y respuestas a tus dudas mas frecuentes.'],
                ['3', 'Crea tus agentes IA',      'Vendedor, soporte, agendador. Define tono, reglas y horario.'],
                ['4', 'Activa y mide',            'La IA atiende, vende y agenda. Tu ves todo en el dashboard.'],
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

<!-- ========================== PRECIOS ========================== -->
<?php if ($showPricing && !empty($plans)): ?>
<section id="precios" class="relative py-20 lg:py-28 border-t border-white/5">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <div class="reveal inline-flex items-center gap-2 px-3 py-1 mb-5 rounded-full bg-amber-500/10 border border-amber-500/20">
                <span class="text-xs font-semibold text-amber-300">PRECIOS TRANSPARENTES</span>
            </div>
            <h2 class="reveal heading-lg text-white mb-4">Elige tu plan</h2>
            <p class="reveal reveal-delay-1 text-lg text-slate-400">Sin sorpresas. Cancela cuando quieras.</p>
        </div>

        <div class="grid md:grid-cols-<?= min(count($plans), 3) ?> gap-4 max-w-5xl mx-auto">
            <?php foreach ($plans as $i => $plan):
                $isPopular = $i === (int) floor(count($plans) / 2);
            ?>
            <div class="reveal relative rounded-2xl p-6 <?= $isPopular ? 'bg-gradient-to-br from-violet-500/10 to-cyan-500/10 border-violet-500/40' : 'bg-white/[0.03] border-white/10' ?> border" style="animation-delay: <?= $i * 0.06 ?>s">
                <?php if ($isPopular): ?>
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-white shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">Mas popular</div>
                <?php endif; ?>
                <h3 class="text-xl font-bold text-white mb-1"><?= e($plan['name']) ?></h3>
                <p class="text-xs text-slate-400 mb-4 min-h-[32px]"><?= e((string) ($plan['description'] ?? '')) ?></p>
                <div class="mb-5">
                    <span class="text-4xl font-bold text-white">$<?= number_format((float) $plan['price_monthly']) ?></span>
                    <span class="text-sm text-slate-500">/mes</span>
                </div>
                <a href="<?= url('/register') ?>" class="block w-full py-2.5 rounded-xl text-center text-sm font-semibold transition <?= $isPopular ? 'text-white shadow-lg shadow-violet-500/30' : 'bg-white/5 text-white hover:bg-white/10' ?>" <?= $isPopular ? 'style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"' : '' ?>>Empezar gratis</a>
                <ul class="mt-5 space-y-2 text-sm text-slate-300">
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> <?= number_format((int) $plan['max_users']) ?> usuarios</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> <?= number_format((int) $plan['max_contacts']) ?> contactos</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> <?= number_format((int) $plan['max_messages']) ?> mensajes/mes</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['ai_enabled']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Agentes IA <?= !empty($plan['ai_enabled']) ? 'incluidos' : 'no incluidos' ?></li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['advanced_reports']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Reportes avanzados</li>
                    <li class="flex items-start gap-2"><svg class="w-4 h-4 <?= !empty($plan['api_access']) ? 'text-emerald-400' : 'text-slate-600' ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg> Acceso API</li>
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
                ['¿La IA realmente puede vender?',           'Si. Le cargas tu catalogo, instrucciones y politicas. La IA negocia, ofrece, agenda y cierra. Tu validas o transfieres a humano cuando lo decidas.'],
                ['¿Que pasa si la IA no entiende algo?',     'Tiene reglas de escalado: tras N intentos fallidos o palabras como "humano", transfiere automaticamente a tu equipo.'],
                ['¿Puedo migrar mis contactos?',             'Si. Importacion CSV, integracion con tus formularios web y API publica para sincronizar con otras herramientas.'],
                ['¿Mis conversaciones son privadas?',        'Si. Cada empresa tiene su data totalmente aislada. No usamos tus conversaciones para entrenar modelos.'],
                ['¿Puedo cancelar cuando quiera?',           'Si. Cancelas con un clic, sin contratos largos ni penalidades.'],
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
    <div class="relative max-w-3xl mx-auto px-6 text-center">
        <h2 class="reveal heading-lg text-white mb-4 text-balance">
            Empieza hoy. <span class="gradient-text-aurora">Vende mas manana.</span>
        </h2>
        <p class="reveal reveal-delay-1 text-lg text-slate-400 mb-7">
            14 dias gratis. Sin tarjeta. Sin compromisos.
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
<footer class="border-t border-white/5 py-10 text-center">
    <div class="max-w-6xl mx-auto px-6">
        <div class="flex items-center justify-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="font-bold text-white"><?= e($brand) ?></span>
        </div>
        <div class="flex items-center justify-center gap-4 mb-4 text-xs text-slate-500 flex-wrap">
            <?php if ($email = $g('contact_email')): ?><a href="mailto:<?= e($email) ?>" class="hover:text-white"><?= e($email) ?></a><?php endif; ?>
            <?php if ($phone = $g('contact_phone')): ?><span><?= e($phone) ?></span><?php endif; ?>
            <a href="<?= url('/changelog') ?>" class="hover:text-white">Changelog</a>
            <a href="<?= url('/login') ?>" class="hover:text-white">Iniciar sesion</a>
        </div>
        <div class="text-xs text-slate-600">
            © <?= date('Y') ?> <?= e($g('legal_company', $brand)) ?>. Todos los derechos reservados.
        </div>
    </div>
</footer>

<?php \App\Core\View::stop(); ?>
