<?php
/**
 * @var array $agent
 * @var array $linked     skills enlazadas con datos de link (priority, is_active, link_config)
 * @var array $available  skills aun no enlazadas a este agente (globales + custom)
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$agentId   = (int) $agent['id'];
$agentName = (string) $agent['name'];
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Inteligencia / Agentes IA',
    'title'    => 'Skills de "' . $agentName . '"',
    'subtitle' => 'Combina capacidades especializadas (ventas, soporte, cobranza, agendamiento...) en este agente. La IA elige la skill mas relevante segun el mensaje del cliente.',
    'back'     => ['href' => url('/settings/ai'), 'label' => 'Volver a agentes'],
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<div class="set-notice set-notice-info">
    <div class="set-notice-icon" style="background: linear-gradient(135deg,#10B981,#06B6D4); color:white;">🧠</div>
    <div class="set-notice-body">
        <div class="set-notice-title">Como funciona el hub de skills</div>
        <p class="set-notice-desc">
            Cada skill aporta su propio prompt + herramientas al agente. Cuando llega un mensaje, un router heuristico
            (sin costo IA) detecta la intencion y prioriza la skill mas relevante. Asi un solo agente puede hacer
            ventas, soporte y cobranza, cambiando de modo segun lo que el cliente diga.
        </p>
    </div>
</div>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>⚡</span> Skills activas <span class="set-count">(<?= count($linked) ?>)</span></h2>
            <p class="set-section-desc">Agente #<?= $agentId ?> · <?= e((string) ($agent['role'] ?? '')) ?></p>
        </div>
    </div>

    <?php if (empty($linked)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">🧩</div>
        <p class="set-empty-text">Aun no has enlazado skills. Anade una abajo para que el agente pueda especializarse.</p>
    </div>
    <?php else: ?>
    <ul class="set-skill-list">
        <?php foreach ($linked as $s):
            $tools = $s['tools'] ? json_decode((string) $s['tools'], true) : [];
            if (!is_array($tools)) $tools = [];
            $isGlobal = empty($s['tenant_id']);
            $linkActive = !empty($s['link_active']);
        ?>
        <li class="set-skill-item <?= $linkActive ? '' : 'is-paused' ?>">
            <div class="set-skill-icon"
                 style="background: <?= $isGlobal ? 'rgba(99,102,241,.12)' : 'rgba(16,185,129,.12)' ?>; color: <?= $isGlobal ? '#6366F1' : '#10B981' ?>;">
                <?= $isGlobal ? '🌐' : '⚡' ?>
            </div>
            <div class="set-skill-body">
                <div class="set-skill-head">
                    <span class="set-skill-name"><?= e((string) $s['name']) ?></span>
                    <code class="set-badge set-badge-mono"><?= e((string) $s['slug']) ?></code>
                    <?php if ($isGlobal): ?>
                    <span class="set-badge" style="background: rgba(99,102,241,.1); color: #4F46E5;">Global</span>
                    <?php else: ?>
                    <span class="set-badge" style="background: rgba(16,185,129,.1); color: #059669;">Custom</span>
                    <?php endif; ?>
                    <?php if (!$linkActive): ?>
                    <span class="set-badge" style="background: rgba(148,163,184,.15); color: #475569;">Pausada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($s['description'])): ?>
                <p class="set-skill-desc"><?= e((string) $s['description']) ?></p>
                <?php endif; ?>
                <?php if ($tools): ?>
                <div class="set-tool-chips">
                    <?php foreach ($tools as $t): ?>
                    <span class="set-tool-chip"><?= e((string) $t) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="set-skill-actions">
                <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/priority') ?>" method="POST" class="set-inline-form">
                    <?= csrf_field() ?>
                    <label class="set-inline-label">Prio</label>
                    <input type="number" name="priority" value="<?= (int) ($s['link_priority'] ?? 100) ?>" min="0" max="999" class="set-input set-input-xs">
                    <button class="set-btn set-btn-ghost set-btn-sm">↑</button>
                </form>
                <div class="set-inline-actions">
                    <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/toggle') ?>" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <button class="set-btn set-btn-ghost set-btn-sm">
                            <?= $linkActive ? '⏸ Pausar' : '▶ Activar' ?>
                        </button>
                    </form>
                    <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/' . (int) $s['id'] . '/detach') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Quitar esta skill del agente?')">
                        <?= csrf_field() ?>
                        <button class="set-btn set-btn-danger set-btn-sm">Quitar</button>
                    </form>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span>➕</span> Disponibles para anadir <span class="set-count">(<?= count($available) ?>)</span></h2>
            <p class="set-section-desc">
                Las skills <strong>globales</strong> son del sistema (sales, support, cobranza, scheduling...). Las <strong>custom</strong> las creas tu desde la pagina de IA.
            </p>
        </div>
    </div>

    <?php if (empty($available)): ?>
    <div class="set-empty">
        <div class="set-empty-icon">✨</div>
        <p class="set-empty-text">Ya tienes todas las skills disponibles enlazadas a este agente.</p>
    </div>
    <?php else: ?>
    <div class="set-skill-grid">
        <?php foreach ($available as $s):
            $tools = $s['tools'] ? json_decode((string) $s['tools'], true) : [];
            if (!is_array($tools)) $tools = [];
            $isGlobal = empty($s['tenant_id']);
        ?>
        <form action="<?= url('/settings/ai/agents/' . $agentId . '/skills/attach') ?>" method="POST" class="set-skill-card">
            <?= csrf_field() ?>
            <input type="hidden" name="slug" value="<?= e((string) $s['slug']) ?>">
            <div class="set-skill-card-head">
                <div class="set-skill-icon"
                     style="background: <?= $isGlobal ? 'rgba(99,102,241,.12)' : 'rgba(16,185,129,.12)' ?>; color: <?= $isGlobal ? '#6366F1' : '#10B981' ?>;">
                    <?= $isGlobal ? '🌐' : '⚡' ?>
                </div>
                <div class="set-skill-card-meta">
                    <div class="set-skill-name"><?= e((string) $s['name']) ?></div>
                    <code class="set-skill-slug"><?= e((string) $s['slug']) ?></code>
                </div>
            </div>
            <?php if (!empty($s['description'])): ?>
            <p class="set-skill-desc"><?= e((string) $s['description']) ?></p>
            <?php endif; ?>
            <?php if ($tools): ?>
            <div class="set-tool-chips">
                <?php foreach (array_slice($tools, 0, 4) as $t): ?>
                <span class="set-tool-chip"><?= e((string) $t) ?></span>
                <?php endforeach; ?>
                <?php if (count($tools) > 4): ?><span class="set-tool-more">+<?= count($tools) - 4 ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="set-skill-card-foot">
                <input type="number" name="priority" value="100" min="0" max="999" class="set-input set-input-xs">
                <button class="set-btn set-btn-success set-btn-sm set-btn-block">+ Anadir al agente</button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
