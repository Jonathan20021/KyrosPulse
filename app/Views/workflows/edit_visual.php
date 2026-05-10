<?php
/**
 * Editor visual drag-drop del workflow engine.
 *
 * Layout (3 columnas en desktop, stack en mobile):
 *   ┌──────────┬──────────────────────────┬──────────┐
 *   │ Palette  │ Canvas (steps en orden)  │ Variables│
 *   │ (types)  │ + drag-drop reordenar    │ + meta   │
 *   └──────────┴──────────────────────────┴──────────┘
 *
 * Usa SortableJS via CDN para drag-drop, Alpine.js para reactividad.
 * Forms tipados: cada step renderiza inputs segun catalog.fields.
 *
 * @var array       $workflow
 * @var array       $steps          (lista cargada server-side; cliente sincroniza luego)
 * @var array       $runs
 * @var string|null $publicUrl
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$cfg = $workflow['trigger_config'] ? json_decode((string) $workflow['trigger_config'], true) : [];
if (!is_array($cfg)) $cfg = [];
$wfId = (int) $workflow['id'];

// Pasamos catalog + variables + agentes al cliente para evitar roundtrip extra.
$catalog   = \App\Services\WorkflowActionCatalog::full();
$agents    = \App\Services\WorkflowActionCatalog::agentsForSelect();
$variables = \App\Services\WorkflowActionCatalog::variablesForWorkflow($workflow);

// Steps inicial serializado (mismo shape que /steps.json)
$initialSteps = array_map(function ($s) {
    $cfg = $s['config'] ? json_decode((string) $s['config'], true) : [];
    if (!is_array($cfg)) $cfg = [];
    return [
        'id'            => (int) $s['id'],
        'step_key'      => (string) $s['step_key'],
        'type'          => (string) $s['type'],
        'order_index'   => (int) $s['order_index'],
        'config'        => $cfg,
        'next_step_key' => $s['next_step_key'] ?? null,
        'branch_yes'    => $s['branch_yes']    ?? null,
        'branch_no'     => $s['branch_no']     ?? null,
    ];
}, $steps);
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => $workflow['name'],
    'subtitle' => 'Editor visual · drag-drop · trigger: ' . $workflow['trigger_type'],
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="mb-4 flex items-center gap-2 flex-wrap">
    <a href="<?= url('/workflows') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">← Volver</a>
    <form action="<?= url('/workflows/' . $wfId . '/toggle') ?>" method="POST" class="inline"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-primary);">
            <?= !empty($workflow['is_active']) ? '⏸ Pausar' : '▶ Activar' ?>
        </button>
    </form>
    <?php if ($workflow['trigger_type'] === 'manual' && !empty($steps)): ?>
    <form action="<?= url('/workflows/' . $wfId . '/run-now') ?>" method="POST" class="inline"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg text-white font-semibold" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">▶ Ejecutar ahora</button>
    </form>
    <?php endif; ?>
    <a href="<?= url('/workflows/' . $wfId . '?mode=json') ?>" class="text-xs px-3 py-1.5 rounded-lg border ml-auto" style="border-color: var(--color-border); color: var(--color-text-secondary);">⚙ Modo JSON crudo</a>
    <form action="<?= url('/workflows/' . $wfId . '/delete') ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar este workflow?')"><?= csrf_field() ?>
        <button class="text-xs px-3 py-1.5 rounded-lg border" style="color:#BE123C; border-color: rgba(244,63,94,.3); background: rgba(244,63,94,.05);">Eliminar</button>
    </form>
</div>

<!-- Trigger info -->
<div class="surface p-4 mb-4">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Trigger</span>
        <span class="text-[10px] px-2 py-0.5 rounded font-mono font-bold" style="background: rgba(139,92,246,.12); color: #8B5CF6;"><?= e((string) $workflow['trigger_type']) ?></span>
        <?php if ($workflow['trigger_type'] === 'event'): ?>
            <span class="text-xs" style="color: var(--color-text-secondary);">cuando ocurre <code><?= e((string) ($cfg['event'] ?? '*')) ?></code></span>
        <?php elseif ($workflow['trigger_type'] === 'schedule'): ?>
            <span class="text-xs" style="color: var(--color-text-secondary);">cron <code><?= e((string) ($cfg['cron'] ?? '?')) ?></code></span>
        <?php elseif ($workflow['trigger_type'] === 'webhook' && $publicUrl): ?>
            <input readonly value="<?= e($publicUrl) ?>" onclick="this.select()" class="text-xs font-mono px-2 py-1 rounded flex-1 min-w-0" style="background: var(--color-bg-secondary); border:1px solid var(--color-border); color: var(--color-text-primary);">
        <?php else: ?>
            <span class="text-xs" style="color: var(--color-text-secondary);">manual</span>
        <?php endif; ?>
    </div>
</div>

<!-- Editor visual -->
<div x-data="workflowEditor()" x-init="init()" class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- Palette -->
    <aside class="lg:col-span-3">
        <div class="surface p-3 sticky top-4">
            <div class="text-[10px] uppercase tracking-wider font-semibold mb-2 px-1" style="color: var(--color-text-tertiary);">Tipos de step</div>
            <div class="space-y-1.5">
                <template x-for="t in catalog.step_types" :key="t.type">
                    <button @click="addStepFromPalette(t)" type="button"
                            class="w-full flex items-start gap-2 px-2.5 py-2 rounded-lg text-left transition hover:scale-[1.01]"
                            :style="`background: rgba(255,255,255,0.02); border:1px solid var(--color-border);`">
                        <span class="text-base flex-shrink-0" x-text="t.icon"></span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold" style="color: var(--color-text-primary);" x-text="t.label"></div>
                            <div class="text-[10px] leading-tight mt-0.5" style="color: var(--color-text-tertiary);" x-text="t.description"></div>
                        </div>
                    </button>
                </template>
            </div>
            <div class="text-[10px] mt-3 px-1" style="color: var(--color-text-tertiary);">
                Click para agregar al final, o arrastra una card del canvas para reordenar.
            </div>
        </div>
    </aside>

    <!-- Canvas -->
    <main class="lg:col-span-6">
        <div class="surface overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--color-border);">
                <h3 class="font-bold text-sm" style="color: var(--color-text-primary);">Flujo
                    <span class="ml-2 text-[10px] font-normal px-1.5 py-0.5 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-tertiary);" x-text="steps.length + ' steps'"></span>
                </h3>
                <div class="flex items-center gap-1">
                    <span x-show="saving" class="text-[11px]" style="color: var(--color-text-tertiary);">guardando...</span>
                    <span x-show="!saving && lastSaved" class="text-[11px]" style="color:#0B7C56;">✓ guardado</span>
                </div>
            </div>

            <!-- Empty state -->
            <div x-show="steps.length === 0" class="p-10 text-center text-sm" style="color: var(--color-text-secondary);">
                <div class="text-4xl mb-2 opacity-60">🪄</div>
                <p class="mb-1" style="color: var(--color-text-primary);">Sin steps todavia</p>
                <p class="text-xs">Click en un tipo de step desde la izquierda para agregarlo aqui.</p>
            </div>

            <!-- Lista sortable -->
            <div id="wf-canvas" x-show="steps.length > 0" class="p-3 space-y-1.5">
                <template x-for="(s, idx) in steps" :key="s.id">
                    <div :data-step-id="s.id"
                         class="step-card group relative rounded-xl transition cursor-move"
                         :style="`background: rgba(255,255,255,0.02); border:1px solid var(--color-border);`">

                        <!-- Conector hacia abajo -->
                        <template x-if="idx < steps.length - 1 && s.type !== 'end' && s.type !== 'branch'">
                            <div class="absolute left-1/2 -translate-x-1/2 -bottom-2 z-10 flex flex-col items-center pointer-events-none">
                                <svg class="w-4 h-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                </svg>
                            </div>
                        </template>

                        <div class="flex items-stretch">
                            <!-- Drag handle -->
                            <div class="drag-handle flex items-center justify-center px-2 cursor-grab active:cursor-grabbing"
                                 style="color: var(--color-text-tertiary);">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 11-.001 4.001A2 2 0 017 2zm0 6a2 2 0 11-.001 4.001A2 2 0 017 8zm0 6a2 2 0 11-.001 4.001A2 2 0 017 14zm6-12a2 2 0 11-.001 4.001A2 2 0 0113 2zm0 6a2 2 0 11-.001 4.001A2 2 0 0113 8zm0 6a2 2 0 11-.001 4.001A2 2 0 0113 14z"/></svg>
                            </div>

                            <!-- Body clickeable -->
                            <button type="button" @click="openEditor(s)" class="flex-1 flex items-start gap-2.5 px-3 py-2.5 text-left">
                                <span class="text-lg flex-shrink-0" x-text="iconForStep(s)"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold"
                                              :style="`background:${colorForStep(s)}22;color:${colorForStep(s)}`"
                                              x-text="s.type"></span>
                                        <span class="font-mono text-sm font-semibold" style="color: var(--color-text-primary);" x-text="s.step_key"></span>
                                    </div>
                                    <div class="text-xs mt-0.5 truncate" style="color: var(--color-text-secondary);" x-text="summaryForStep(s)"></div>
                                </div>
                            </button>

                            <!-- Acciones rapidas -->
                            <div class="flex items-center gap-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" @click.stop="cloneStep(s)" title="Duplicar"
                                        class="p-1 rounded hover:bg-white/10" style="color: var(--color-text-tertiary);">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                                <button type="button" @click.stop="deleteStep(s)" title="Eliminar"
                                        class="p-1 rounded hover:bg-red-500/10" style="color:#BE123C;">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Branches inline (si type=branch) -->
                        <template x-if="s.type === 'branch'">
                            <div class="border-t flex" style="border-color: var(--color-border);">
                                <div class="flex-1 px-3 py-1.5 border-r" style="border-color: var(--color-border);">
                                    <span class="text-[10px] uppercase tracking-wider font-semibold" style="color:#0B7C56;">si TRUE</span>
                                    <span class="text-xs ml-1.5 font-mono" style="color: var(--color-text-secondary);" x-text="s.branch_yes || '(sin destino)'"></span>
                                </div>
                                <div class="flex-1 px-3 py-1.5">
                                    <span class="text-[10px] uppercase tracking-wider font-semibold" style="color:#BE123C;">si FALSE</span>
                                    <span class="text-xs ml-1.5 font-mono" style="color: var(--color-text-secondary);" x-text="s.branch_no || '(sin destino)'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <!-- Variable picker -->
    <aside class="lg:col-span-3">
        <div class="surface p-3 sticky top-4">
            <div class="text-[10px] uppercase tracking-wider font-semibold mb-2 px-1" style="color: var(--color-text-tertiary);">Variables disponibles</div>
            <p class="text-[10px] mb-2 px-1" style="color: var(--color-text-tertiary);">Click para copiar al portapapeles. Pegalas como <code>{{ path }}</code> en cualquier campo de texto.</p>

            <template x-for="(group, gname) in groupedVariables" :key="gname">
                <div class="mb-2.5">
                    <div class="text-[10px] font-bold mb-1 px-1" style="color: var(--color-text-secondary);" x-text="gname"></div>
                    <div class="space-y-0.5">
                        <template x-for="v in group" :key="v.path">
                            <button @click="copyVar(v.path)" type="button"
                                    class="w-full flex items-start gap-1.5 px-2 py-1 rounded text-left text-[11px] transition hover:bg-white/5"
                                    :style="`color: var(--color-text-primary)`">
                                <code class="font-mono text-[10px]" x-text="'{{ ' + v.path + ' }}'"></code>
                                <span class="text-[9px] ml-auto opacity-60 leading-tight" x-text="v.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </aside>
</div>

<!-- Modal de edicion de step -->
<div x-show="editing" x-cloak x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
     style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);"
     @click.self="closeEditor()">
    <div x-show="editing" x-transition class="w-full max-w-2xl rounded-2xl p-5 my-auto"
         style="background: var(--color-bg-card); border:1px solid var(--color-border); box-shadow:0 30px 80px rgba(0,0,0,0.5);"
         @keydown.escape.window="closeEditor()">

        <template x-if="editing">
            <div>
                <!-- Header -->
                <div class="flex items-start gap-3 mb-4 pb-3 border-b" style="border-color: var(--color-border);">
                    <span class="text-2xl flex-shrink-0" x-text="iconForStep(editing)"></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">
                            <span x-text="editing.type"></span><span x-show="editing.type === 'action' && editing.config && editing.config.action"> · </span><span x-text="editing.config?.action || ''"></span>
                        </div>
                        <input x-model="editing.step_key" :readonly="editing._isNew !== true"
                               class="font-mono text-base font-bold bg-transparent border-0 p-0 focus:outline-none w-full mt-0.5"
                               style="color: var(--color-text-primary);"
                               placeholder="step_key (ej: send_welcome)">
                        <div x-show="editing._isNew" class="text-[10px] mt-0.5" style="color: var(--color-text-tertiary);">
                            Solo a-z, 0-9, _, max 40 chars. Una vez creado, el key no se puede cambiar.
                        </div>
                    </div>
                    <button type="button" @click="closeEditor()" class="text-2xl leading-none px-2" style="color: var(--color-text-tertiary);">&times;</button>
                </div>

                <!-- Form fields tipados -->
                <div class="space-y-3 max-h-[55vh] overflow-y-auto pr-1">
                    <template x-for="f in fieldsFor(editing)" :key="f.key">
                        <div>
                            <label class="text-xs font-semibold block mb-1" style="color: var(--color-text-primary);">
                                <span x-text="f.label"></span>
                                <span x-show="f.required" class="text-red-500">*</span>
                            </label>
                            <p x-show="f.help" class="text-[10px] mb-1" style="color: var(--color-text-tertiary);" x-text="f.help"></p>

                            <!-- text / number -->
                            <input x-show="f.type === 'text' || f.type === 'number'"
                                   :type="f.type === 'number' ? 'number' : 'text'"
                                   :placeholder="f.placeholder || ''"
                                   :value="cfgValue(f.key)"
                                   @input="setCfg(f.key, f.type === 'number' ? ($event.target.value === '' ? null : Number($event.target.value)) : $event.target.value)"
                                   class="input">

                            <!-- textarea -->
                            <textarea x-show="f.type === 'textarea'"
                                      :rows="f.rows || 3"
                                      :placeholder="f.placeholder || ''"
                                      :value="cfgValue(f.key)"
                                      @input="setCfg(f.key, $event.target.value)"
                                      class="input font-mono text-sm"></textarea>

                            <!-- duration -->
                            <div x-show="f.type === 'duration'" class="flex gap-2">
                                <input type="number" min="1"
                                       :value="durationParts(cfgValue(f.key) || f.default || 60).n"
                                       @input="setCfg(f.key, durationToSec($event.target.value, durationParts(cfgValue(f.key) || f.default || 60).unit))"
                                       class="input flex-1">
                                <select :value="durationParts(cfgValue(f.key) || f.default || 60).unit"
                                        @change="setCfg(f.key, durationToSec(durationParts(cfgValue(f.key) || f.default || 60).n, $event.target.value))"
                                        class="select w-32">
                                    <option value="s">segundos</option>
                                    <option value="m">minutos</option>
                                    <option value="h">horas</option>
                                    <option value="d">dias</option>
                                </select>
                            </div>

                            <!-- select -->
                            <select x-show="f.type === 'select'"
                                    :value="cfgValue(f.key) || f.default || ''"
                                    @change="setCfg(f.key, $event.target.value); if (f.key === 'action') $nextTick(() => {})"
                                    class="select">
                                <option value="">— Selecciona —</option>
                                <template x-for="o in f.options" :key="o.value">
                                    <option :value="o.value" x-text="o.label" :selected="cfgValue(f.key) == o.value"></option>
                                </template>
                            </select>

                            <!-- agent_select -->
                            <select x-show="f.type === 'agent_select'"
                                    :value="cfgValue(f.key) || ''"
                                    @change="setCfg(f.key, $event.target.value === '' ? null : Number($event.target.value))"
                                    class="select">
                                <option value="">— Selecciona agente —</option>
                                <template x-for="a in agents" :key="a.value">
                                    <option :value="a.value" x-text="a.label" :selected="cfgValue(f.key) == a.value"></option>
                                </template>
                            </select>

                            <!-- kv (key-value pairs) -->
                            <div x-show="f.type === 'kv'" class="space-y-1.5">
                                <template x-for="(pair, i) in kvPairs(f.key)" :key="i">
                                    <div class="flex gap-2">
                                        <input :value="pair.key" @input="setKvKey(f.key, i, $event.target.value)" placeholder="header-name" class="input flex-1">
                                        <input :value="pair.value" @input="setKvValue(f.key, i, $event.target.value)" placeholder="valor" class="input flex-1">
                                        <button type="button" @click="removeKv(f.key, i)" class="px-2 rounded text-red-500 hover:bg-red-500/10">−</button>
                                    </div>
                                </template>
                                <button type="button" @click="addKv(f.key)" class="text-xs px-2 py-1 rounded" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">+ Agregar</button>
                            </div>
                        </div>
                    </template>

                    <!-- Conexiones -->
                    <template x-if="editing.type !== 'end'">
                        <div class="pt-3 border-t" style="border-color: var(--color-border);">
                            <div class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Conexiones</div>
                            <template x-if="editing.type !== 'branch'">
                                <div>
                                    <label class="text-xs font-semibold block mb-1" style="color: var(--color-text-primary);">Siguiente step</label>
                                    <select x-model="editing.next_step_key" class="select">
                                        <option value="">— Fin del flujo —</option>
                                        <template x-for="other in otherSteps(editing)" :key="other.id">
                                            <option :value="other.step_key" x-text="other.step_key"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="editing.type === 'branch'">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs font-semibold block mb-1" style="color:#0B7C56;">Si TRUE → ir a</label>
                                        <select x-model="editing.branch_yes" class="select">
                                            <option value="">— Fin —</option>
                                            <template x-for="other in otherSteps(editing)" :key="other.id">
                                                <option :value="other.step_key" x-text="other.step_key"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold block mb-1" style="color:#BE123C;">Si FALSE → ir a</label>
                                        <select x-model="editing.branch_no" class="select">
                                            <option value="">— Fin —</option>
                                            <template x-for="other in otherSteps(editing)" :key="other.id">
                                                <option :value="other.step_key" x-text="other.step_key"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between gap-2 mt-4 pt-3 border-t" style="border-color: var(--color-border);">
                    <button type="button" @click="closeEditor()" class="px-3 py-1.5 rounded-lg text-sm border" style="border-color: var(--color-border); color: var(--color-text-primary);">Cancelar</button>
                    <button type="button" @click="saveStep()" :disabled="saving"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">
                        <span x-show="!saving">Guardar step</span>
                        <span x-show="saving">Guardando...</span>
                    </button>
                </div>

                <!-- Error -->
                <div x-show="editorError" class="mt-3 text-xs p-2.5 rounded-lg" style="background: rgba(244,63,94,.08); color:#BE123C;" x-text="editorError"></div>
            </div>
        </template>
    </div>
</div>

<!-- Runs recientes (debajo del editor) -->
<div class="surface mt-6 overflow-hidden">
    <div class="px-5 py-3 border-b" style="border-color: var(--color-border);">
        <h3 class="font-bold text-sm" style="color: var(--color-text-primary);">Runs recientes</h3>
    </div>
    <?php if (empty($runs)): ?>
    <div class="p-6 text-center text-xs" style="color: var(--color-text-secondary);">Sin ejecuciones aun.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary); background: var(--color-bg-secondary);">
                    <th class="text-left px-4 py-2">Cuando</th>
                    <th class="text-left px-4 py-2">Trigger</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Step actual</th>
                    <th class="text-right px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($runs as $r):
                    $col = match ($r['status']) {
                        'succeeded' => '#0B7C56',
                        'failed','cancelled' => '#BE123C',
                        'waiting' => '#B45309',
                        'running' => '#06B6D4',
                        default   => '#64748B',
                    };
                ?>
                <tr class="border-t" style="border-color: var(--color-border);">
                    <td class="px-4 py-2 text-xs" style="color: var(--color-text-secondary);"><?= e((string) $r['created_at']) ?></td>
                    <td class="px-4 py-2 text-xs"><code><?= e((string) $r['trigger_type']) ?></code></td>
                    <td class="px-4 py-2"><span class="font-semibold text-xs" style="color: <?= $col ?>;"><?= e((string) $r['status']) ?></span></td>
                    <td class="px-4 py-2 text-xs"><code><?= e((string) ($r['current_step_key'] ?? '—')) ?></code></td>
                    <td class="px-4 py-2 text-right"><a href="<?= url('/workflows/' . $wfId . '/runs/' . (int) $r['id']) ?>" class="text-xs px-2 py-0.5 rounded font-semibold" style="background: var(--color-bg-secondary); color: var(--color-text-primary);">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- SortableJS via CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
window.WF_ID         = <?= (int) $wfId ?>;
window.WF_CSRF       = <?= json_encode(csrf_token()) ?>;
window.WF_BASE       = <?= json_encode(url('/workflows/' . $wfId)) ?>;
window.WF_INITIAL    = <?= json_encode($initialSteps, JSON_UNESCAPED_UNICODE) ?>;
window.WF_CATALOG    = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
window.WF_AGENTS     = <?= json_encode($agents, JSON_UNESCAPED_UNICODE) ?>;
window.WF_VARIABLES  = <?= json_encode($variables, JSON_UNESCAPED_UNICODE) ?>;

function workflowEditor() {
    return {
        catalog:   window.WF_CATALOG,
        agents:    window.WF_AGENTS,
        variables: window.WF_VARIABLES,
        steps:     [...window.WF_INITIAL],
        editing:   null,
        editorError: '',
        saving:    false,
        lastSaved: false,
        sortable:  null,

        init() {
            // Drag-drop reorder con SortableJS
            this.$nextTick(() => {
                const el = document.getElementById('wf-canvas');
                if (!el || !window.Sortable) return;
                this.sortable = Sortable.create(el, {
                    handle: '.drag-handle',
                    animation: 180,
                    ghostClass: 'opacity-40',
                    onEnd: () => this.persistOrder(),
                });
            });
            // Atajo: Ctrl+S guarda el step abierto si hay
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's' && this.editing) {
                    e.preventDefault();
                    this.saveStep();
                }
            });
        },

        get groupedVariables() {
            const groups = {};
            for (const v of this.variables) {
                const g = v.group || 'Otros';
                if (!groups[g]) groups[g] = [];
                groups[g].push(v);
            }
            return groups;
        },

        // ----- Helpers UI -----
        iconForStep(s) {
            if (s.type === 'action') {
                const a = (s.config && s.config.action) ? this.catalog.actions[s.config.action] : null;
                if (a && a.icon) return a.icon;
            }
            const t = this.catalog.step_types.find(x => x.type === s.type);
            return t ? t.icon : '⚙';
        },
        colorForStep(s) {
            const t = this.catalog.step_types.find(x => x.type === s.type);
            return t ? t.color : '#64748B';
        },
        summaryForStep(s) {
            const c = s.config || {};
            switch (s.type) {
                case 'action':
                    if (!c.action) return '(sin accion seleccionada)';
                    const meta = this.catalog.actions[c.action];
                    if (!meta) return c.action;
                    return meta.label + (c.params ? this.summarizeParams(c.action, c.params) : '');
                case 'branch':
                    return `${c.expr || '?'} ${c.op || 'truthy'} ${c.value !== undefined ? c.value : ''}`.trim();
                case 'delay':
                    const sec = Number(c.seconds || 0);
                    if (sec >= 86400) return `${Math.round(sec/86400)} dias`;
                    if (sec >= 3600)  return `${Math.round(sec/3600)} horas`;
                    if (sec >= 60)    return `${Math.round(sec/60)} minutos`;
                    return `${sec} segundos`;
                case 'set_var':
                    return `${c.key || '?'} = ${typeof c.value === 'string' ? c.value.substring(0, 40) : '...'}`;
                case 'end':
                    return `final: ${c.status || 'succeeded'}`;
            }
            return '';
        },
        summarizeParams(action, params) {
            if (!params || typeof params !== 'object') return '';
            const summarizers = {
                send_whatsapp: () => params.text ? ` · "${String(params.text).substring(0, 30)}..."` : '',
                run_agent: () => params.agent_id ? ` · agente #${params.agent_id}` : '',
                http: () => params.url ? ` · ${params.method || 'GET'} ${String(params.url).substring(0, 30)}` : '',
            };
            const fn = summarizers[action];
            return fn ? fn() : '';
        },
        otherSteps(s) {
            return this.steps.filter(x => x.id !== s.id);
        },

        // ----- Field helpers -----
        fieldsFor(step) {
            if (!step) return [];
            const t = this.catalog.step_types.find(x => x.type === step.type);
            const baseFields = t ? [...t.fields] : [];
            if (step.type === 'action' && step.config && step.config.action) {
                const a = this.catalog.actions[step.config.action];
                if (a && a.fields) {
                    // Inyectamos los fields de params bajo la key "params.X"
                    for (const f of a.fields) {
                        baseFields.push({ ...f, key: 'params.' + f.key });
                    }
                }
            }
            return baseFields;
        },
        cfgValue(path) {
            if (!this.editing) return '';
            return this.getDeep(this.editing.config || {}, path);
        },
        setCfg(path, value) {
            if (!this.editing.config) this.editing.config = {};
            this.setDeep(this.editing.config, path, value);
        },
        getDeep(obj, path) {
            const parts = path.split('.');
            let cur = obj;
            for (const p of parts) {
                if (cur && typeof cur === 'object' && p in cur) cur = cur[p];
                else return undefined;
            }
            return cur;
        },
        setDeep(obj, path, value) {
            const parts = path.split('.');
            let cur = obj;
            for (let i = 0; i < parts.length - 1; i++) {
                const p = parts[i];
                if (!cur[p] || typeof cur[p] !== 'object') cur[p] = {};
                cur = cur[p];
            }
            cur[parts[parts.length - 1]] = value;
        },

        // KV pairs (para headers HTTP)
        kvPairs(path) {
            const obj = this.cfgValue(path);
            if (!obj || typeof obj !== 'object') return [];
            return Object.entries(obj).map(([k, v]) => ({ key: k, value: v }));
        },
        addKv(path) {
            const obj = this.cfgValue(path) || {};
            obj[''] = '';
            this.setCfg(path, { ...obj });
        },
        setKvKey(path, idx, newKey) {
            const pairs = this.kvPairs(path);
            pairs[idx].key = newKey;
            const obj = {};
            for (const p of pairs) if (p.key !== '') obj[p.key] = p.value;
            this.setCfg(path, obj);
        },
        setKvValue(path, idx, newVal) {
            const pairs = this.kvPairs(path);
            pairs[idx].value = newVal;
            const obj = {};
            for (const p of pairs) if (p.key !== '') obj[p.key] = p.value;
            this.setCfg(path, obj);
        },
        removeKv(path, idx) {
            const pairs = this.kvPairs(path);
            pairs.splice(idx, 1);
            const obj = {};
            for (const p of pairs) if (p.key !== '') obj[p.key] = p.value;
            this.setCfg(path, obj);
        },

        // Duration helper
        durationParts(seconds) {
            seconds = Number(seconds || 0);
            if (seconds % 86400 === 0 && seconds >= 86400) return { n: seconds / 86400, unit: 'd' };
            if (seconds % 3600  === 0 && seconds >= 3600)  return { n: seconds / 3600,  unit: 'h' };
            if (seconds % 60    === 0 && seconds >= 60)    return { n: seconds / 60,    unit: 'm' };
            return { n: seconds, unit: 's' };
        },
        durationToSec(n, unit) {
            n = Number(n) || 0;
            switch (unit) {
                case 'd': return n * 86400;
                case 'h': return n * 3600;
                case 'm': return n * 60;
                default:  return n;
            }
        },

        // ----- Acciones -----
        addStepFromPalette(t) {
            const stepKey = this.suggestKey(t.type);
            const defaultConfig = {};
            // Aplicar defaults declarativos
            for (const f of (t.fields || [])) {
                if (f.default !== undefined) defaultConfig[f.key] = f.default;
            }
            if (t.type === 'delay' && !defaultConfig.seconds) defaultConfig.seconds = 60;

            this.editing = {
                _isNew: true,
                id: 'new_' + Date.now(),
                step_key: stepKey,
                type: t.type,
                config: defaultConfig,
                next_step_key: null,
                branch_yes: null,
                branch_no: null,
            };
            this.editorError = '';
        },

        suggestKey(type) {
            const base = type === 'action' ? 'step' : type;
            let n = 1;
            while (this.steps.some(s => s.step_key === `${base}_${n}`)) n++;
            return `${base}_${n}`;
        },

        openEditor(step) {
            // Clonamos para no mutar la lista hasta guardar
            this.editing = JSON.parse(JSON.stringify(step));
            this.editorError = '';
        },
        closeEditor() {
            this.editing = null;
            this.editorError = '';
        },

        async saveStep() {
            this.editorError = '';
            this.saving = true;
            try {
                if (this.editing._isNew) {
                    const r = await this.api('POST', `${window.WF_BASE}/steps.json`, {
                        step_key: this.editing.step_key,
                        type: this.editing.type,
                        config: this.editing.config || {},
                        next_step_key: this.editing.next_step_key || null,
                        branch_yes: this.editing.branch_yes || null,
                        branch_no: this.editing.branch_no || null,
                    });
                    if (r.error) {
                        this.editorError = r.message || r.error;
                        this.saving = false;
                        return;
                    }
                    this.steps = [...this.steps, r.data];
                    // Auto-conectar el step previo a este si era el ultimo y no era branch/end
                    await this.autoLinkPrevious(r.data);
                } else {
                    const r = await this.api('PATCH', `${window.WF_BASE}/steps/${this.editing.id}.json`, {
                        config: this.editing.config || {},
                        next_step_key: this.editing.next_step_key || null,
                        branch_yes: this.editing.branch_yes || null,
                        branch_no: this.editing.branch_no || null,
                    });
                    if (r.error) {
                        this.editorError = r.message || r.error;
                        this.saving = false;
                        return;
                    }
                    const idx = this.steps.findIndex(s => s.id === r.data.id);
                    if (idx >= 0) this.steps.splice(idx, 1, r.data);
                }
                this.flashSaved();
                this.closeEditor();
            } catch (e) {
                this.editorError = 'Error de red: ' + e.message;
            }
            this.saving = false;
        },

        async autoLinkPrevious(newStep) {
            // Si el step anterior es action/set_var/delay y no tiene next, conectalo
            if (this.steps.length < 2) return;
            const prev = this.steps[this.steps.length - 2];
            if (!prev || prev.type === 'end' || prev.type === 'branch') return;
            if (prev.next_step_key) return;
            await this.api('PATCH', `${window.WF_BASE}/steps/${prev.id}.json`, {
                next_step_key: newStep.step_key,
            });
            prev.next_step_key = newStep.step_key;
        },

        async cloneStep(s) {
            const baseKey = s.step_key.replace(/_(\d+)$/, '');
            let n = 2;
            while (this.steps.some(x => x.step_key === `${baseKey}_${n}`)) n++;
            const clone = JSON.parse(JSON.stringify(s));
            clone._isNew = true;
            clone.id = 'new_' + Date.now();
            clone.step_key = `${baseKey}_${n}`;
            this.editing = clone;
        },

        async deleteStep(s) {
            if (!confirm(`Eliminar step "${s.step_key}"?`)) return;
            const r = await this.api('POST', `${window.WF_BASE}/steps/${s.id}/delete.json`);
            if (r.error) { alert('Error: ' + r.error); return; }
            this.steps = this.steps.filter(x => x.id !== s.id);
            this.flashSaved();
        },

        async persistOrder() {
            const ids = Array.from(document.querySelectorAll('#wf-canvas [data-step-id]'))
                .map(el => Number(el.getAttribute('data-step-id')));
            const r = await this.api('POST', `${window.WF_BASE}/steps/reorder.json`, { order: ids });
            if (r.error) { alert('Error reordenando: ' + r.error); return; }
            // Sincronizar con server (orden + auto-rewire)
            this.steps = r.data;
            this.flashSaved();
        },

        flashSaved() {
            this.lastSaved = true;
            setTimeout(() => this.lastSaved = false, 2000);
        },

        async copyVar(path) {
            const text = `{{ ${path} }}`;
            try {
                await navigator.clipboard.writeText(text);
            } catch (_) {
                // Fallback
                const ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
            }
        },

        async api(method, url, body = null) {
            try {
                const opts = {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': window.WF_CSRF,
                    },
                };
                if (body !== null) {
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(body);
                }
                const resp = await fetch(url, opts);
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) return { error: data.error || ('HTTP ' + resp.status), message: data.message || null };
                return data;
            } catch (e) {
                return { error: e.message };
            }
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.step-card.opacity-40 { opacity: 0.4; }
</style>
<?php \App\Core\View::end(); ?>
