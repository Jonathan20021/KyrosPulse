<?php
/** @var array $template */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$color    = (string) ($template['accent_color'] ?? '#8B5CF6');
$icon     = (string) ($template['icon'] ?? '🤖');
$name     = (string) ($template['name'] ?? 'Agente IA');
$slug     = (string) ($template['slug'] ?? '');
$desc     = (string) ($template['description'] ?? '');
$tone     = (string) ($template['default_tone'] ?? '');
$role     = (string) ($template['default_role'] ?? '');
$chans    = (array)  ($template['default_channels'] ?? []);
$trigKws  = (array)  ($template['default_trigger_keywords'] ?? []);
$questions = (array) ($template['questions'] ?? []);
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Inteligencia / Agentes IA / Wizard',
    'title'    => 'Configurar ' . $name,
    'subtitle' => $desc,
    'back'     => ['href' => url('/settings/ai/wizard'), 'label' => 'Volver a plantillas'],
]); ?>

<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<form action="<?= url('/settings/ai/wizard/' . urlencode($slug)) ?>" method="POST">
    <?= csrf_field() ?>

    <section class="set-section set-tpl-header" style="--tpl-color: <?= e($color) ?>;">
        <div class="set-tpl-header-icon" style="background: <?= e($color) ?>;"><?= e($icon) ?></div>
        <div class="set-tpl-header-body">
            <h2 class="set-tpl-header-name"><?= e($name) ?></h2>
            <p class="set-tpl-header-desc">Esta plantilla configurara automaticamente: rol "<strong><?= e($role) ?></strong>", tono "<strong><?= e($tone) ?></strong>", canal<?= count($chans) === 1 ? '' : 'es' ?> <strong><?= e(implode(', ', $chans) ?: '—') ?></strong>, y <?= count($trigKws) ?> palabras clave de activacion. <a href="#" onclick="document.getElementById('whatThis').showModal(); return false;" class="set-link">¿Que significa esto?</a></p>
        </div>
    </section>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>📝</span> Nombre interno del agente</h2>
                <p class="set-section-desc">Solo lo veras tu y tu equipo. Si lo dejas vacio usamos "<?= e($name) ?>".</p>
            </div>
        </div>
        <div class="set-field">
            <input type="text" name="name" maxlength="120" placeholder="<?= e($name) ?>" class="set-input">
        </div>
    </section>

    <?php foreach ($questions as $i => $q):
        $qKey  = (string) ($q['key'] ?? '');
        $qLbl  = (string) ($q['label'] ?? '');
        $qPh   = (string) ($q['placeholder'] ?? '');
        $qType = (string) ($q['type'] ?? 'text');
        $qReq  = !empty($q['required']);
        if ($qKey === '') continue;
    ?>
    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title">
                    <span class="set-tpl-step-num" style="background: <?= e($color) ?>;"><?= $i + 1 ?></span>
                    <?= e($qLbl) ?>
                    <?php if ($qReq): ?><span class="req">*</span><?php endif; ?>
                </h2>
            </div>
        </div>
        <div class="set-field">
            <?php if ($qType === 'textarea'): ?>
            <textarea name="answers[<?= e($qKey) ?>]"
                      rows="<?= str_contains($qPh, "\n") ? 5 : 4 ?>"
                      <?= $qReq ? 'required' : '' ?>
                      placeholder="<?= e($qPh) ?>"
                      class="set-textarea"></textarea>
            <?php else: ?>
            <input type="text" name="answers[<?= e($qKey) ?>]"
                   <?= $qReq ? 'required' : '' ?>
                   placeholder="<?= e($qPh) ?>"
                   maxlength="200"
                   class="set-input">
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="set-section">
        <div class="set-section-head">
            <div>
                <h2 class="set-section-title"><span>🚦</span> Ultimo paso</h2>
                <p class="set-section-desc">Activa el agente cuando termines.</p>
            </div>
        </div>
        <div class="set-field-row cols-2 set-field">
            <label class="set-check">
                <input type="checkbox" name="auto_reply_enabled" value="1" checked>
                <div class="set-check-body">
                    <div class="set-check-title">Responder automaticamente</div>
                    <div class="set-check-desc">La IA contesta sola los mensajes entrantes que disparen este agente. Si lo desactivas, el agente queda en "borrador".</div>
                </div>
            </label>
            <label class="set-check">
                <input type="checkbox" name="is_default" value="1">
                <div class="set-check-body">
                    <div class="set-check-title">Agente principal (fallback)</div>
                    <div class="set-check-desc">Recibe los mensajes que no caen en ningun otro agente. Solo puede haber uno.</div>
                </div>
            </label>
        </div>
    </section>

    <div class="set-actions">
        <a href="<?= url('/settings/ai/wizard') ?>" class="set-btn set-btn-ghost">Cancelar</a>
        <button type="submit" class="set-btn set-btn-primary" style="background: linear-gradient(135deg, <?= e($color) ?>, #06B6D4);">
            ✨ Crear agente
        </button>
    </div>
</form>

<dialog id="whatThis" class="set-modal">
    <div class="set-modal-body">
        <h3 class="set-modal-title">Que va a configurar esta plantilla por ti</h3>
        <ul class="set-modal-list">
            <li><strong>Rol</strong>: <?= e($role) ?></li>
            <li><strong>Tono de voz</strong>: <?= e($tone) ?></li>
            <li><strong>Canales donde opera</strong>: <?= e(implode(', ', $chans) ?: 'todos') ?></li>
            <li><strong>Palabras clave que activan al agente</strong>: <?= e(implode(', ', array_slice($trigKws, 0, 8))) ?><?= count($trigKws) > 8 ? '…' : '' ?></li>
            <li><strong>Reintentos antes de escalar</strong>: <?= (int) ($template['default_max_retries'] ?? 3) ?></li>
            <li><strong>Prioridad</strong>: <?= (int) ($template['default_priority'] ?? 100) ?></li>
        </ul>
        <p class="set-help">Todo esto se puede ajustar despues desde el "Modo avanzado" (visible solo para Owner / Admin).</p>
        <div class="set-actions" style="justify-content: flex-end;">
            <button type="button" class="set-btn set-btn-primary" onclick="document.getElementById('whatThis').close()">Entendido</button>
        </div>
    </div>
</dialog>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
