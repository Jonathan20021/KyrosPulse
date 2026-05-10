<?php
/**
 * Banner que aparece si el tenant salteo el onboarding o aun esta en progreso.
 * Se incluye desde dashboard.executive.php (y otros lugares prominentes).
 */
$tenant = \App\Core\Tenant::current();
if (!$tenant) return;

$completed = !empty($tenant['onboarding_completed_at']);
$skipped   = !empty($tenant['onboarding_skipped']);
$step      = (int) ($tenant['onboarding_step'] ?? 0);

// No mostrar si ya completo Y no salteo (es decir, completo normalmente)
if ($completed && !$skipped) return;
// Si salteo, mostrar un banner mas suave invitando a retomar
?>
<?php if ($skipped): ?>
<div class="surface p-3.5 mb-4 flex items-center gap-3" style="background: linear-gradient(135deg, rgba(139,92,246,.06), rgba(6,182,212,.04)); border-color: rgba(139,92,246,.2);">
    <div class="text-xl flex-shrink-0">🎯</div>
    <div class="flex-1 min-w-0">
        <div class="font-semibold text-sm" style="color: var(--color-text-primary);">Setup rapido (3 min)</div>
        <p class="text-xs" style="color: var(--color-text-secondary);">Saltaste el wizard. ¿Lo retomamos? Configura WhatsApp + agente IA + primer workflow en pocos clicks.</p>
    </div>
    <form action="<?= url('/onboarding/resume') ?>" method="POST" class="flex-shrink-0">
        <?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg font-semibold text-white" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
            Retomar setup →
        </button>
    </form>
</div>
<?php elseif (!$completed): ?>
<div class="surface p-3.5 mb-4 flex items-center gap-3" style="background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(244,63,94,.04)); border-color: rgba(245,158,11,.3);">
    <div class="text-xl flex-shrink-0">⚙</div>
    <div class="flex-1 min-w-0">
        <div class="font-semibold text-sm" style="color: var(--color-text-primary);">Tu setup esta a medias</div>
        <p class="text-xs" style="color: var(--color-text-secondary);">Te quedan <?= 5 - $step ?> pasos para tener tu operacion automatica corriendo.</p>
    </div>
    <a href="<?= url('/onboarding') ?>" class="flex-shrink-0 text-xs px-3 py-1.5 rounded-lg font-semibold text-white" style="background: linear-gradient(135deg,#F59E0B,#EF4444);">
        Continuar →
    </a>
</div>
<?php endif; ?>
