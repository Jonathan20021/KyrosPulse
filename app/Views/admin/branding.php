<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
$settings = $settings ?? [];
$g = fn(string $k, string $d = '') => (string) ($settings[$k] ?? $d);
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white mb-1">Branding y landing</h1>
    <p class="text-sm text-slate-400">Personaliza copy, contacto y secciones visibles en <a href="<?= url('/') ?>" target="_blank" class="text-cyan-400 hover:underline">la pagina publica</a>.</p>
</div>

<form action="<?= url('/admin/branding') ?>" method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <!-- Marca -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">🏷️</span> Marca</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Nombre de marca</label>
                <input type="text" name="brand_name" value="<?= e($g('brand_name')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Tagline corto</label>
                <input type="text" name="brand_tagline" value="<?= e($g('brand_tagline')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Razon social legal</label>
                <input type="text" name="legal_company" value="<?= e($g('legal_company')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>
    </div>

    <!-- Hero -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">⚡</span> Hero (encabezado del landing)</h3>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Eyebrow (texto pequeno arriba)</label>
                <input type="text" name="hero_eyebrow" value="<?= e($g('hero_eyebrow')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Titular principal</label>
                <textarea name="hero_headline" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm"><?= e($g('hero_headline')) ?></textarea>
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Subtitulo</label>
                <textarea name="hero_sub" rows="3" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm"><?= e($g('hero_sub')) ?></textarea>
            </div>
        </div>
    </div>

    <!-- CTAs -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">🎯</span> Botones de accion</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">CTA primario · texto</label>
                <input type="text" name="cta_primary_label" value="<?= e($g('cta_primary_label')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">CTA primario · URL</label>
                <input type="text" name="cta_primary_url" value="<?= e($g('cta_primary_url')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">CTA secundario · texto</label>
                <input type="text" name="cta_secondary_label" value="<?= e($g('cta_secondary_label')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">CTA secundario · URL</label>
                <input type="text" name="cta_secondary_url" value="<?= e($g('cta_secondary_url')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>
    </div>

    <!-- Contacto -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">📞</span> Contacto y redes</h3>
        <div class="grid md:grid-cols-3 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Email</label>
                <input type="email" name="contact_email" value="<?= e($g('contact_email')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Telefono</label>
                <input type="text" name="contact_phone" value="<?= e($g('contact_phone')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">WhatsApp link</label>
                <input type="text" name="contact_whatsapp" value="<?= e($g('contact_whatsapp')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">X / Twitter</label>
                <input type="text" name="social_x" value="<?= e($g('social_x')) ?>" placeholder="https://x.com/..." class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">LinkedIn</label>
                <input type="text" name="social_linkedin" value="<?= e($g('social_linkedin')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Instagram</label>
                <input type="text" name="social_instagram" value="<?= e($g('social_instagram')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>
    </div>

    <!-- Secciones publicas -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">🪟</span> Secciones publicas</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <label class="flex items-center justify-between gap-2 px-3 py-3 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
                <span class="text-sm text-slate-300">Mostrar precios en landing</span>
                <input type="checkbox" name="show_pricing" value="1" <?= !empty($settings['show_pricing']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
            </label>
            <label class="flex items-center justify-between gap-2 px-3 py-3 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
                <span class="text-sm text-slate-300">Mostrar /changelog publico</span>
                <input type="checkbox" name="show_changelog" value="1" <?= !empty($settings['show_changelog']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
            </label>
        </div>
    </div>

    <div class="flex justify-end sticky bottom-3">
        <button type="submit" class="px-6 py-2.5 rounded-xl text-white text-sm font-semibold shadow-xl shadow-violet-500/40" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
            Guardar cambios
        </button>
    </div>
</form>

<?php \App\Core\View::stop(); ?>
