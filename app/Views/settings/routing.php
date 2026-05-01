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
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Routing inteligente',
    'subtitle' => 'Define como se asignan automaticamente las conversaciones que llegan: por canal, palabras clave, horario, etiquetas o score. Cada regla se evalua en orden de prioridad.',
]); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'routing']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php
    $totalRules  = count($rules);
    $activeRules = count(array_filter($rules, fn ($r) => !empty($r['is_active'])));
    $totalExec   = array_sum(array_map(fn ($r) => (int) ($r['executions_count'] ?? 0), $rules));
    $aiRouted    = count(array_filter($rules, fn ($r) => $r['assign_strategy'] === 'ai_agent'));
    foreach ([
        ['🎯', 'Reglas creadas',     $totalRules,  '#7C3AED'],
        ['✅', 'Activas',            $activeRules, '#10B981'],
        ['⚡', 'Ejecuciones totales', $totalExec,   '#06B6D4'],
        ['🤖', 'Con IA asignada',    $aiRouted,    '#F59E0B'],
    ] as [$em, $lbl, $val, $col]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $em ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Form crear/editar -->
<div x-data="{ open: false, editing: null }" class="mb-5">
    <button @click="open = !open; editing = null" type="button" class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nueva regla de routing
    </button>

    <div x-show="open" x-cloak x-transition class="surface mt-4 p-5">
        <form :action="editing ? '<?= url('/settings/routing/') ?>' + editing.id : '<?= url('/settings/routing') ?>'" method="POST" class="space-y-4" x-data="{ matchType: 'any', strategy: 'round_robin' }" x-init="if (editing) { matchType = editing.match_type; strategy = editing.assign_strategy; }">
            <?= csrf_field() ?>
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <div class="grid md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="label">Nombre de la regla</label>
                    <input type="text" name="name" required maxlength="120" placeholder="Ej: VIP US → Asignar a Maria"
                           :value="editing ? editing.name : ''" class="input">
                </div>
                <div>
                    <label class="label">Prioridad <span class="text-[10px]" style="color: var(--color-text-tertiary);">(menor = se evalua primero)</span></label>
                    <input type="number" name="priority" min="1" max="999" value="100" :value="editing ? editing.priority : 100" class="input">
                </div>
                <div>
                    <label class="label">Canal/numero (opcional)</label>
                    <select name="channel_id" class="input">
                        <option value="">Cualquier canal</option>
                        <?php foreach ($channels as $ch): ?>
                        <option value="<?= (int) $ch['id'] ?>" :selected="editing && editing.channel_id == <?= (int) $ch['id'] ?>"><?= e($ch['label']) ?> · <?= e($ch['phone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Condicion -->
            <fieldset class="rounded-2xl p-4 border" style="border-color: var(--color-border-subtle);">
                <legend class="text-[10px] uppercase font-bold tracking-wider px-2" style="color: var(--color-primary);">¿Cuando se aplica?</legend>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label">Condicion</label>
                        <select name="match_type" x-model="matchType" class="input">
                            <?php foreach ($matchTypes as $key => $lbl): ?>
                            <option value="<?= $key ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="matchType !== 'any'" x-transition>
                        <label class="label">
                            <span x-text="{
                                keyword: 'Palabras clave (separadas por coma)',
                                channel: 'ID de canal',
                                time: 'Configuracion JSON de horario',
                                language: 'Idioma (es, en, pt)',
                                contact_tag: 'Nombre de la etiqueta',
                                contact_score: 'Score minimo (0-100)',
                            }[matchType] || 'Valor'"></span>
                        </label>
                        <input type="text" name="match_value" :value="editing ? editing.match_value : ''" class="input"
                               placeholder="precio, cuanto cuesta, comprar">
                    </div>
                </div>
            </fieldset>

            <!-- Accion -->
            <fieldset class="rounded-2xl p-4 border" style="border-color: var(--color-border-subtle);">
                <legend class="text-[10px] uppercase font-bold tracking-wider px-2" style="color: var(--color-primary);">¿Que hace?</legend>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label">Estrategia de asignacion</label>
                        <select name="assign_strategy" x-model="strategy" class="input">
                            <?php foreach ($strategies as $key => $lbl): ?>
                            <option value="<?= $key ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div x-show="strategy === 'specific_user'" x-transition>
                        <label class="label">Usuario</label>
                        <select name="assign_user_id" class="input">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($agents as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?> (<?= e((string) ($a['email'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div x-show="strategy === 'team' || strategy === 'round_robin' || strategy === 'least_busy'" x-transition>
                        <label class="label">Equipo / rol (opcional)</label>
                        <select name="assign_role" class="input">
                            <option value="">Todos los agentes</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div x-show="strategy === 'ai_agent'" x-transition>
                        <label class="label">Agente IA</label>
                        <select name="assign_ai_agent_id" class="input">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($aiAgents as $aa): ?>
                            <option value="<?= (int) $aa['id'] ?>"><?= e($aa['name']) ?> · <?= e((string) ($aa['role'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div x-show="strategy === 'ai_agent'" x-transition class="md:col-span-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="auto_reply_enabled" value="1" class="w-4 h-4 rounded">
                            <span class="text-sm" style="color: var(--color-text-secondary);">Activar auto-pilot (la IA responde automaticamente)</span>
                        </label>
                    </div>
                </div>
            </fieldset>

            <!-- Extras -->
            <fieldset class="rounded-2xl p-4 border" style="border-color: var(--color-border-subtle);">
                <legend class="text-[10px] uppercase font-bold tracking-wider px-2" style="color: var(--color-primary);">Acciones adicionales</legend>
                <div class="grid md:grid-cols-3 gap-3">
                    <div>
                        <label class="label">Etiqueta automatica</label>
                        <input type="text" name="auto_tag" placeholder="VIP, Cobranza, Hot lead..." :value="editing ? editing.auto_tag : ''" class="input">
                    </div>
                    <div>
                        <label class="label">Prioridad</label>
                        <select name="auto_priority" class="input">
                            <option value="">— Sin cambio —</option>
                            <option value="low">Baja</option>
                            <option value="normal">Normal</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded">
                            <span class="text-sm" style="color: var(--color-text-secondary);">Activar inmediatamente</span>
                        </label>
                    </div>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="open = false; editing = null" class="px-4 py-2 rounded-xl text-sm" style="color: var(--color-text-secondary);">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                    <span x-text="editing ? 'Guardar cambios' : 'Crear regla'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lista -->
<?php if (empty($rules)): ?>
<div class="surface p-10 text-center">
    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center text-3xl" style="background: var(--gradient-mesh);">🎯</div>
    <h3 class="font-bold text-lg mb-1" style="color: var(--color-text-primary);">Sin reglas de routing</h3>
    <p class="text-sm mb-4" style="color: var(--color-text-tertiary);">Crea tu primera regla para que las conversaciones se asignen solas al agente correcto.</p>
    <div class="grid sm:grid-cols-3 gap-3 max-w-2xl mx-auto text-left">
        <?php foreach ([
            ['Round-robin VIP', 'Distribuye automaticamente entre tu equipo de ventas.', '🔄'],
            ['IA primero', 'Que la IA conteste, escala a humano si pide.', '🤖'],
            ['Por idioma', 'Mensajes en ingles → equipo bilingue.', '🌍'],
        ] as [$t, $d, $em]): ?>
        <div class="rounded-xl p-3 border" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle);">
            <div class="text-2xl mb-1"><?= $em ?></div>
            <div class="font-semibold text-sm mb-0.5" style="color: var(--color-text-primary);"><?= e($t) ?></div>
            <div class="text-xs" style="color: var(--color-text-tertiary);"><?= e($d) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($rules as $rule):
        $strategyLbl = $strategies[$rule['assign_strategy']] ?? $rule['assign_strategy'];
        $matchLbl = $matchTypes[$rule['match_type']] ?? $rule['match_type'];
        $isActive = !empty($rule['is_active']);
    ?>
    <div class="surface p-4">
        <div class="flex items-start gap-3 flex-wrap">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                <?= (int) $rule['priority'] ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h3 class="font-bold text-sm" style="color: var(--color-text-primary);"><?= e($rule['name']) ?></h3>
                    <span class="flex items-center gap-1 text-[10px] font-semibold" style="color: <?= $isActive ? '#34D399' : '#F87171' ?>;">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $isActive ? '#10B981' : '#EF4444' ?>;"></span>
                        <?= $isActive ? 'Activa' : 'Pausada' ?>
                    </span>
                    <?php if (!empty($rule['channel_label'])): ?>
                    <span class="text-[10px] font-semibold px-1.5 rounded" style="background: <?= e((string) ($rule['channel_color'] ?? '#7C3AED')) ?>22; color: <?= e((string) ($rule['channel_color'] ?? '#7C3AED')) ?>;">
                        <?= e((string) $rule['channel_label']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 flex-wrap text-xs" style="color: var(--color-text-tertiary);">
                    <span>📋 <?= e($matchLbl) ?></span>
                    <?php if (!empty($rule['match_value'])): ?><span class="font-mono px-1.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);">"<?= e(mb_strimwidth((string) $rule['match_value'], 0, 30, '...')) ?>"</span><?php endif; ?>
                    <span>→</span>
                    <span class="font-semibold" style="color: var(--color-primary);"><?= e($strategyLbl) ?></span>
                    <?php if (!empty($rule['first_name'])): ?>
                    <span>· <?= e(($rule['first_name'] ?? '') . ' ' . ($rule['last_name'] ?? '')) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($rule['ai_agent_name'])): ?>
                    <span class="px-1.5 rounded" style="background: rgba(124,58,237,.15); color:#A78BFA;">🤖 <?= e($rule['ai_agent_name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rule['auto_tag']) || !empty($rule['auto_priority']) || !empty($rule['auto_reply_enabled'])): ?>
                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap text-[10px]">
                    <?php if (!empty($rule['auto_tag'])): ?><span class="px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,.15); color:#F59E0B;">+ Tag: <?= e((string) $rule['auto_tag']) ?></span><?php endif; ?>
                    <?php if (!empty($rule['auto_priority'])): ?><span class="px-1.5 py-0.5 rounded" style="background: rgba(244,63,94,.15); color:#F87171;">Priority: <?= e((string) $rule['auto_priority']) ?></span><?php endif; ?>
                    <?php if (!empty($rule['auto_reply_enabled'])): ?><span class="px-1.5 py-0.5 rounded" style="background: rgba(16,185,129,.15); color:#34D399;">Auto-pilot</span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-1 ml-auto">
                <span class="text-[10px] hidden sm:inline" style="color: var(--color-text-muted);">
                    <?= number_format((int) ($rule['executions_count'] ?? 0)) ?> ejecuciones
                </span>
                <form action="<?= url('/settings/routing/' . $rule['id'] . '/toggle') ?>" method="POST" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);">
                        <?= $isActive ? 'Pausar' : 'Activar' ?>
                    </button>
                </form>
                <form action="<?= url('/settings/routing/' . $rule['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar esta regla?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs" style="color: #F87171;">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
