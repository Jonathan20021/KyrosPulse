<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
$entries    = $entries    ?? [];
$categories = $categories ?? [];
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Changelog</h1>
        <p class="text-sm text-slate-400">Comunica novedades del SaaS a tus clientes en <a href="<?= url('/changelog') ?>" target="_blank" class="text-cyan-400 hover:underline">/changelog</a></p>
    </div>
    <button onclick="document.getElementById('newEntryForm').scrollIntoView({behavior:'smooth'})" class="px-4 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">+ Nueva entrada</button>
</div>

<!-- Listado existente -->
<?php if (empty($entries)): ?>
<div class="admin-card rounded-2xl p-10 text-center mb-6">
    <div class="text-5xl mb-3">📰</div>
    <h3 class="font-bold text-white mb-1">Aun no hay entradas</h3>
    <p class="text-sm text-slate-400">Crea la primera abajo.</p>
</div>
<?php else: ?>
<div class="space-y-3 mb-6">
    <?php foreach ($entries as $e):
        $cat = (string) ($e['category'] ?? 'feature');
        [$catLabel, $catColor] = $categories[$cat] ?? ['Otro', '#A78BFA'];
        $tags = $e['tags'] ? (json_decode((string) $e['tags'], true) ?: []) : [];
    ?>
    <details class="admin-card rounded-2xl p-4">
        <summary class="cursor-pointer list-none flex items-center justify-between gap-3 flex-wrap">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <?php if (!empty($e['version'])): ?>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/5 text-slate-400">v<?= e($e['version']) ?></span>
                    <?php endif; ?>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded" style="background: <?= $catColor ?>22; color: <?= $catColor ?>;"><?= e($catLabel) ?></span>
                    <?php if (!empty($e['is_featured'])): ?><span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/15 text-amber-400">★ Destacado</span><?php endif; ?>
                    <?php if (empty($e['is_published'])): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-400">Borrador</span>
                    <?php else: ?>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300">Publicada</span>
                    <?php endif; ?>
                    <?php foreach ($tags as $tg): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-white/5 text-slate-500">#<?= e($tg) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="font-bold text-white"><?= e($e['title']) ?></div>
                <?php if (!empty($e['summary'])): ?>
                <div class="text-sm text-slate-400 mt-1"><?= e((string) $e['summary']) ?></div>
                <?php endif; ?>
            </div>
            <div class="text-right text-xs text-slate-500 flex-shrink-0">
                <?= !empty($e['published_at']) ? date('d M Y', strtotime((string) $e['published_at'])) : '—' ?>
            </div>
        </summary>

        <form action="<?= url('/admin/changelog/' . $e['id']) ?>" method="POST" class="mt-4 pt-4 border-t border-white/5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <div class="grid md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Titulo</label>
                    <input type="text" name="title" value="<?= e($e['title']) ?>" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Version</label>
                    <input type="text" name="version" value="<?= e((string) ($e['version'] ?? '')) ?>" placeholder="2.4.0" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Categoria</label>
                    <select name="category" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                        <?php foreach ($categories as $val => [$label, $color]): ?>
                        <option value="<?= e($val) ?>" <?= $cat === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Autor</label>
                    <input type="text" name="author" value="<?= e((string) ($e['author'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Publicar el</label>
                    <input type="datetime-local" name="published_at" value="<?= !empty($e['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $e['published_at'])) : '' ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Resumen (1-2 frases)</label>
                    <input type="text" name="summary" value="<?= e((string) ($e['summary'] ?? '')) ?>" maxlength="500" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Cuerpo (markdown soportado)</label>
                    <textarea name="body" rows="6" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono"><?= e((string) ($e['body'] ?? '')) ?></textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Tags (coma)</label>
                    <input type="text" name="tags" value="<?= e(implode(', ', $tags)) ?>" placeholder="ia, whatsapp, automatizacion" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_published" value="1" <?= !empty($e['is_published']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    Publicada
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_featured" value="1" <?= !empty($e['is_featured']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    Destacada
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/5">
                <form action="<?= url('/admin/changelog/' . $e['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="px-3 py-1.5 rounded-lg text-xs text-rose-400 hover:bg-rose-500/10">Eliminar</button>
                </form>
                <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar cambios</button>
            </div>
        </form>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Nueva entrada -->
<form id="newEntryForm" action="<?= url('/admin/changelog') ?>" method="POST" class="admin-card rounded-2xl p-5">
    <?= csrf_field() ?>
    <h3 class="font-bold text-white mb-3">Nueva entrada</h3>
    <div class="grid md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Titulo</label>
            <input type="text" name="title" required placeholder="Soporte multi-agente IA" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Version</label>
            <input type="text" name="version" placeholder="2.5.0" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Categoria</label>
            <select name="category" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                <?php foreach ($categories as $val => [$label]): ?>
                <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Autor</label>
            <input type="text" name="author" placeholder="Equipo Producto" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Publicar el</label>
            <input type="datetime-local" name="published_at" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Resumen</label>
            <input type="text" name="summary" maxlength="500" placeholder="Que cambia y por que importa" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Cuerpo</label>
            <textarea name="body" rows="6" placeholder="- Bullet 1
- Bullet 2

Detalles tecnicos opcionales..." class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono"></textarea>
        </div>
        <div class="md:col-span-3">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Tags (coma)</label>
            <input type="text" name="tags" placeholder="ia, ventas, whatsapp" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_published" value="1" checked class="w-4 h-4 rounded">
            Publicar inmediatamente
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 rounded">
            Marcar como destacada
        </label>
    </div>
    <button type="submit" class="mt-4 px-5 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear entrada</button>
</form>

<?php \App\Core\View::stop(); ?>
