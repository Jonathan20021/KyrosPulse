<?php
/** @var array $templates */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Inteligencia / Agentes IA',
    'title'    => 'Crear un agente IA',
    'subtitle' => 'Elige una plantilla y responde 3-4 preguntas. La IA queda funcional en menos de un minuto, sin tocar campos tecnicos.',
    'back'     => ['href' => url('/settings/ai'), 'label' => 'Volver a agentes'],
]); ?>

<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if (empty($templates)): ?>
<section class="set-section">
    <div class="set-empty">
        <div class="set-empty-icon">🤖</div>
        <h3 class="set-empty-title">Sin plantillas disponibles</h3>
        <p class="set-empty-desc">El administrador del SaaS no ha publicado plantillas todavia. Si tu rol es Owner / Admin, puedes usar el "Modo avanzado" en la pagina de Agentes IA para crear uno desde cero.</p>
    </div>
</section>
<?php else: ?>
<div class="set-card-grid">
    <?php foreach ($templates as $t):
        $color = (string) ($t['accent_color'] ?? '#8B5CF6');
        $qCount = is_array($t['questions'] ?? null) ? count($t['questions']) : 0;
    ?>
    <a href="<?= url('/settings/ai/wizard/' . urlencode((string) $t['slug'])) ?>" class="set-tpl-card" style="--tpl-color: <?= e($color) ?>;">
        <div class="set-tpl-icon" style="background: <?= e($color) ?>;">
            <?= e((string) ($t['icon'] ?? '🤖')) ?>
        </div>
        <h3 class="set-tpl-name"><?= e((string) $t['name']) ?></h3>
        <p class="set-tpl-desc"><?= e((string) ($t['description'] ?? '')) ?></p>
        <div class="set-tpl-foot">
            <span class="set-tpl-meta"><?= $qCount ?> pregunta<?= $qCount === 1 ? '' : 's' ?> · ~30 seg</span>
            <span class="set-tpl-arrow">→</span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="set-notice set-notice-info" style="margin-top: 20px;">
    <div class="set-notice-icon">💡</div>
    <div class="set-notice-body">
        <div class="set-notice-title">¿No encuentras la plantilla ideal?</div>
        <p class="set-notice-desc">Elige la mas parecida — luego puedes ajustar las instrucciones cuando quieras. Si necesitas total control sobre prompts, palabras clave o canales, pide al Owner del workspace que active el "Modo avanzado" en la pagina de Agentes IA.</p>
    </div>
</div>
<?php endif; ?>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
