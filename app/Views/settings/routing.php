<?php
/** @var array $rules */
/** @var array $channels */
/** @var array $agents */
/** @var array $roles */
/** @var array $aiAgents */
/** @var array $matchTypes */
/** @var array $strategies */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'routing']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Canales',
    'title'    => 'Routing inteligente',
    'subtitle' => 'Define como se asignan automaticamente las conversaciones que llegan: por canal, palabras clave, horario, etiquetas o score. Cada regla se evalua en orden de prioridad.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php
$totalRules  = count($rules);
$activeRules = count(array_filter($rules, fn ($r) => !empty($r['is_active'])));
$totalExec   = array_sum(array_map(fn ($r) => (int) ($r['executions_count'] ?? 0), $rules));
$aiRouted    = count(array_filter($rules, fn ($r) => $r['assign_strategy'] === 'ai_agent'));
?>
<div class="set-kpi-grid">
    <?php foreach ([
        ['🎯', 'Reglas creadas',     $totalRules,  '#10B981'],
        ['✅', 'Activas',            $activeRules, '#10B981'],
        ['⚡', 'Ejecuciones totales', $totalExec,   '#06B6D4'],
        ['🤖', 'Con IA asignada',    $aiRouted,    '#F59E0B'],
    ] as [$em, $lbl, $val, $col]): ?>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label"><?= e($lbl) ?></span>
            <span class="set-kpi-emoji"><?= $em ?></span>
        </div>
        <div class="set-kpi-value" style="color: <?= $col ?>;"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div x-data="{ open: false, editing: null }" class="set-actions-bar">
    <button @click="open = !open; editing = null" type="button" class="set-btn set-btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nueva regla de routing
    </button>

    <section x-show="open" x-cloak x-transition class="set-section" style="margin-top: 16px;">
        <form :action="editing ? '<?= url('/settings/routing/') ?>' + editing.id : '<?= url('/settings/routing') ?>'" method="POST" x-data="{ matchType: 'any', strategy: 'round_robin' }" x-init="if (editing) { matchType = editing.match_type; strategy = editing.assign_strategy; }">
            <?= csrf_field() ?>
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <div class="set-field-row cols-2 set-field">
                <div style="grid-column: 1 / -1;">
                    <label class="set-label">Nombre de la regla</label>
                    <input type="text" name="name" required maxlength="120" placeholder="Ej: VIP US → Asignar a Maria"
                           :value="editing ? editing.name : ''" class="set-input">
                </div>
                <div>
                    <label class="set-label">Prioridad <span class="set-help-inline">(menor = se evalua primero)</span></label>
                    <input type="number" name="priority" min="1" max="999" value="100" :value="editing ? editing.priority : 100" class="set-input">
                </div>
                <div>
                    <label class="set-label">Canal/numero (opcional)</label>
                    <select name="channel_id" class="set-select">
                        <option value="">Cualquier canal</option>
                        <?php foreach ($channels as $ch): ?>
                        <option value="<?= (int) $ch['id'] ?>"><?= e($ch['label']) ?> · <?= e($ch['phone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <fieldset class="set-fieldset">
                <legend>¿Cuando se aplica?</legend>
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Condicion</label>
                        <select name="match_type" x-model="matchType" class="set-select">
                            <?php foreach ($matchTypes as $key => $lbl): ?>
                            <option value="<?= $key ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="matchType !== 'any'" x-transition>
                        <label class="set-label">
                            <span x-text="{
                                keyword: 'Palabras clave (separadas por coma)',
                                channel: 'ID de canal',
                                time: 'Configuracion JSON de horario',
                                language: 'Idioma (es, en, pt)',
                                contact_tag: 'Nombre de la etiqueta',
                                contact_score: 'Score minimo (0-100)',
                            }[matchType] || 'Valor'"></span>
                        </label>
                        <input type="text" name="match_value" :value="editing ? editing.match_value : ''" class="set-input"
                               placeholder="precio, cuanto cuesta, comprar">
                    </div>
                </div>
            </fieldset>

            <fieldset class="set-fieldset">
                <legend>¿Que hace?</legend>
                <div class="set-field-row cols-2 set-field">
                    <div>
                        <label class="set-label">Estrategia de asignacion</label>
                        <select name="assign_strategy" x-model="strategy" class="set-select">
                            <?php foreach ($strategies as $key => $lbl): ?>
                            <option value="<?= $key ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="strategy === 'specific_user'" x-transition>
                        <label class="set-label">Usuario</label>
                        <select name="assign_user_id" class="set-select">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($agents as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?> (<?= e((string) ($a['email'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="strategy === 'team' || strategy === 'round_robin' || strategy === 'least_busy'" x-transition>
                        <label class="set-label">Equipo / rol (opcional)</label>
                        <select name="assign_role" class="set-select">
                            <option value="">Todos los agentes</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="strategy === 'ai_agent'" x-transition>
                        <label class="set-label">Agente IA</label>
                        <select name="assign_ai_agent_id" class="set-select">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($aiAgents as $aa): ?>
                            <option value="<?= (int) $aa['id'] ?>"><?= e($aa['name']) ?> · <?= e((string) ($aa['role'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="strategy === 'ai_agent'" x-transition style="grid-column: 1 / -1;">
                        <label class="set-check">
                            <input type="checkbox" name="auto_reply_enabled" value="1">
                            <div class="set-check-body">
                                <div class="set-check-title">Activar auto-pilot</div>
                                <div class="set-check-desc">La IA responde automaticamente sin intervencion humana.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </fieldset>

            <fieldset class="set-fieldset">
                <legend>Acciones adicionales</legend>
                <div class="set-field-row cols-3 set-field">
                    <div>
                        <label class="set-label">Etiqueta automatica</label>
                        <input type="text" name="auto_tag" placeholder="VIP, Cobranza, Hot lead..." :value="editing ? editing.auto_tag : ''" class="set-input">
                    </div>
                    <div>
                        <label class="set-label">Prioridad</label>
                        <select name="auto_priority" class="set-select">
                            <option value="">— Sin cambio —</option>
                            <option value="low">Baja</option>
                            <option value="normal">Normal</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div style="display:flex; align-items:flex-end;">
                        <label class="set-check" style="margin-bottom: 4px;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <div class="set-check-body">
                                <div class="set-check-title">Activar inmediatamente</div>
                            </div>
                        </label>
                    </div>
                </div>
            </fieldset>

            <div class="set-actions">
                <button type="button" @click="open = false; editing = null" class="set-btn set-btn-ghost">Cancelar</button>
                <button type="submit" class="set-btn set-btn-primary">
                    <span x-text="editing ? 'Guardar cambios' : 'Crear regla'"></span>
                </button>
            </div>
        </form>
    </section>
</div>

<?php if (empty($rules)): ?>
<section class="set-section">
    <div class="set-empty">
        <div class="set-empty-icon">🎯</div>
        <h3 class="set-empty-title">Sin reglas de routing</h3>
        <p class="set-empty-desc">Crea tu primera regla para que las conversaciones se asignen solas al agente correcto.</p>
    </div>
    <div class="set-hint-grid">
        <?php foreach ([
            ['Round-robin VIP', 'Distribuye automaticamente entre tu equipo de ventas.', '🔄'],
            ['IA primero', 'Que la IA conteste, escala a humano si pide.', '🤖'],
            ['Por idioma', 'Mensajes en ingles → equipo bilingue.', '🌍'],
        ] as [$t, $d, $em]): ?>
        <div class="set-hint-card">
            <div class="set-hint-emoji"><?= $em ?></div>
            <div class="set-hint-title"><?= e($t) ?></div>
            <div class="set-hint-desc"><?= e($d) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<div class="set-rule-stack">
    <?php foreach ($rules as $rule):
        $strategyLbl = $strategies[$rule['assign_strategy']] ?? $rule['assign_strategy'];
        $matchLbl = $matchTypes[$rule['match_type']] ?? $rule['match_type'];
        $isActive = !empty($rule['is_active']);
    ?>
    <div class="set-section set-route-card">
        <div class="set-route-prio"><?= (int) $rule['priority'] ?></div>
        <div class="set-route-body">
            <div class="set-route-head">
                <h3 class="set-route-name"><?= e($rule['name']) ?></h3>
                <span class="set-route-status" style="color: <?= $isActive ? '#10B981' : '#F87171' ?>;">
                    <span class="set-int-dot" style="background: <?= $isActive ? '#10B981' : '#EF4444' ?>;"></span>
                    <?= $isActive ? 'Activa' : 'Pausada' ?>
                </span>
                <?php if (!empty($rule['channel_label'])): ?>
                <span class="set-badge" style="background: <?= e((string) ($rule['channel_color'] ?? '#10B981')) ?>22; color: <?= e((string) ($rule['channel_color'] ?? '#10B981')) ?>;">
                    <?= e((string) $rule['channel_label']) ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="set-route-meta">
                <span>📋 <?= e($matchLbl) ?></span>
                <?php if (!empty($rule['match_value'])): ?>
                <code class="set-badge set-badge-mono">"<?= e(mb_strimwidth((string) $rule['match_value'], 0, 30, '...')) ?>"</code>
                <?php endif; ?>
                <span>→</span>
                <span class="set-route-strategy"><?= e($strategyLbl) ?></span>
                <?php if (!empty($rule['first_name'])): ?>
                <span>· <?= e(($rule['first_name'] ?? '') . ' ' . ($rule['last_name'] ?? '')) ?></span>
                <?php endif; ?>
                <?php if (!empty($rule['ai_agent_name'])): ?>
                <span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;">🤖 <?= e($rule['ai_agent_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($rule['auto_tag']) || !empty($rule['auto_priority']) || !empty($rule['auto_reply_enabled'])): ?>
            <div class="set-route-extras">
                <?php if (!empty($rule['auto_tag'])): ?><span class="set-badge" style="background: rgba(245,158,11,.15); color:#F59E0B;">+ Tag: <?= e((string) $rule['auto_tag']) ?></span><?php endif; ?>
                <?php if (!empty($rule['auto_priority'])): ?><span class="set-badge" style="background: rgba(244,63,94,.15); color:#F87171;">Priority: <?= e((string) $rule['auto_priority']) ?></span><?php endif; ?>
                <?php if (!empty($rule['auto_reply_enabled'])): ?><span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;">Auto-pilot</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="set-route-actions">
            <span class="set-route-count"><?= number_format((int) ($rule['executions_count'] ?? 0)) ?> ejecuciones</span>
            <form action="<?= url('/settings/routing/' . $rule['id'] . '/toggle') ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="set-btn set-btn-ghost set-btn-sm">
                    <?= $isActive ? 'Pausar' : 'Activar' ?>
                </button>
            </form>
            <form action="<?= url('/settings/routing/' . $rule['id']) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar esta regla?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="set-btn set-btn-danger set-btn-sm">Eliminar</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
