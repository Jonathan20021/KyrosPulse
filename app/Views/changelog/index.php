<?php
/** @var array $entries */
/** @var array $grouped */
/** @var array $categories */
\App\Core\View::extend('layouts.landing');
\App\Core\View::start('content');

$brand = (string) (\App\Models\SaasSetting::get('brand_name', 'Kyros Pulse'));
?>

<!-- Nav -->
<nav id="mainNav" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="flex items-center gap-2 text-white font-bold">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/30" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-lg"><?= e($brand) ?></span>
        </a>
        <div class="flex items-center gap-2">
            <a href="<?= url('/') ?>" class="text-sm text-slate-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5 transition">← Inicio</a>
            <a href="<?= url('/login') ?>" class="text-sm text-slate-300 hover:text-white px-3 py-1.5 transition">Iniciar sesion</a>
            <a href="<?= url('/register') ?>" class="text-sm font-semibold text-white px-4 py-1.5 rounded-lg" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Empezar gratis</a>
        </div>
    </div>
</nav>

<section class="relative pt-32 pb-12 overflow-hidden">
    <div class="absolute top-0 -left-20 w-96 h-96 bg-violet-600 rounded-full blur-[120px] opacity-20"></div>
    <div class="absolute top-20 -right-20 w-96 h-96 bg-cyan-500 rounded-full blur-[120px] opacity-15"></div>

    <div class="relative max-w-3xl mx-auto px-6">
        <div class="reveal text-center mb-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 mb-6 rounded-full border border-white/10 bg-white/5 backdrop-blur-sm">
                <span class="live-dot"></span>
                <span class="text-xs font-medium text-slate-300">Mejoramos cada semana</span>
            </div>
            <h1 class="heading-lg text-white text-balance mb-4">Changelog de <?= e($brand) ?></h1>
            <p class="text-lg text-slate-400 text-balance">Todas las novedades, mejoras y correcciones que entregamos al producto.</p>
        </div>

        <?php if (empty($entries)): ?>
        <div class="text-center py-20">
            <div class="text-6xl mb-4 opacity-50">📰</div>
            <p class="text-slate-400">Pronto compartiremos novedades aqui.</p>
        </div>
        <?php else: ?>

        <!-- Timeline -->
        <div class="relative">
            <!-- Linea vertical -->
            <div class="absolute left-4 md:left-6 top-2 bottom-2 w-px bg-gradient-to-b from-violet-500/40 via-white/10 to-transparent"></div>

            <?php foreach ($entries as $i => $e):
                $cat = (string) ($e['category'] ?? 'feature');
                [$catLabel, $catColor] = $categories[$cat] ?? ['Otro', '#A78BFA'];
                $tags = !empty($e['tags']) ? (json_decode((string) $e['tags'], true) ?: []) : [];
                $when = !empty($e['published_at']) ? strtotime((string) $e['published_at']) : strtotime((string) $e['created_at']);
            ?>
            <article class="reveal relative pl-12 md:pl-16 pb-10" style="animation-delay: <?= ($i * 0.05) ?>s;">
                <!-- Punto -->
                <div class="absolute left-2 md:left-4 top-1 w-5 h-5 rounded-full border-2 flex items-center justify-center backdrop-blur" style="border-color: <?= $catColor ?>; background: rgba(5,8,23,.95); box-shadow: 0 0 24px <?= $catColor ?>55;">
                    <div class="w-2 h-2 rounded-full" style="background: <?= $catColor ?>;"></div>
                </div>

                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 md:p-6 hover:border-white/20 transition <?= !empty($e['is_featured']) ? 'ring-1 ring-amber-500/30' : '' ?>">
                    <div class="flex items-center gap-2 flex-wrap mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded" style="background: <?= $catColor ?>22; color: <?= $catColor ?>;">
                            <?= e($catLabel) ?>
                        </span>
                        <?php if (!empty($e['version'])): ?>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-white/5 text-slate-400">v<?= e($e['version']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($e['is_featured'])): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/15 text-amber-400 font-semibold">★ Destacado</span>
                        <?php endif; ?>
                        <span class="text-xs text-slate-500 ml-auto"><?= date('d M Y', $when) ?></span>
                    </div>

                    <h2 class="text-xl md:text-2xl font-bold text-white mb-2"><?= e($e['title']) ?></h2>

                    <?php if (!empty($e['summary'])): ?>
                    <p class="text-slate-300 leading-relaxed mb-3"><?= e((string) $e['summary']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($e['body'])): ?>
                    <div class="prose prose-sm prose-invert max-w-none mt-3 text-slate-400">
                        <?php
                        // Renderizado simple: respetar saltos de linea y bullets, escapar HTML.
                        $body = e((string) $e['body']);
                        $body = preg_replace('/^- (.+)$/m', '<li>$1</li>', $body);
                        $body = preg_replace('/(<li>[\s\S]+?<\/li>)/', '<ul class="list-disc pl-5 my-2 space-y-1">$0</ul>', $body);
                        $body = preg_replace('/\*\*(.+?)\*\*/u', '<strong class="text-white">$1</strong>', $body);
                        $body = nl2br($body);
                        echo $body;
                        ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tags) || !empty($e['author'])): ?>
                    <div class="mt-4 pt-3 border-t border-white/5 flex items-center gap-2 flex-wrap text-xs">
                        <?php if (!empty($e['author'])): ?>
                        <span class="text-slate-500">Por <span class="text-slate-300 font-medium"><?= e((string) $e['author']) ?></span></span>
                        <?php endif; ?>
                        <?php foreach ($tags as $tg): ?>
                        <span class="px-1.5 py-0.5 rounded bg-white/5 text-slate-400">#<?= e($tg) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

        <!-- CTA al final -->
        <div class="mt-12 text-center reveal">
            <p class="text-slate-400 mb-4">¿Tienes una idea o pedido?</p>
            <a href="<?= url('/register') ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white text-sm font-semibold shadow-xl shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">
                Empezar gratis y ser parte
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </div>
</section>

<?php \App\Core\View::stop(); ?>
