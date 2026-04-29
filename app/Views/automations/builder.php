<?php
/**
 * @var array|null $automation
 * @var array $errors
 * @var array $triggers
 * @var array $conditionTypes
 * @var array $actionTypes
 * @var array $agents
 * @var array $stages
 * @var array|null $logs
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$isEdit  = !empty($automation);
$conditions = $isEdit ? ($automation['conditions_arr'] ?? []) : [];
$actions    = $isEdit ? ($automation['actions_arr']    ?? []) : [];
$logs       = $logs ?? [];
?>

<?php \App\Core\View::include('components.page_header', [
    'title' => $isEdit ? 'Editar automatizacion' : 'Nueva automatizacion',
    'subtitle' => 'Define cuando se dispara, condiciones a cumplir y que acciones ejecutar.',
]); ?>

<form action="<?= $isEdit ? url('/automations/' . $automation['id']) : url('/automations') ?>" method="POST" class="grid lg:grid-cols-3 gap-4">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <!-- Configuracion -->
    <div class="lg:col-span-2 space-y-4">
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Informacion</h3>
            <div class="grid md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre *</label>
                    <input type="text" name="name" required value="<?= e((string) ($automation['name'] ?? old('name'))) ?>"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Descripcion</label>
                    <textarea name="description" rows="2"
                              class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($automation['description'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Disparador *</label>
                    <select name="trigger_event" required class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <?php foreach ($triggers as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($automation['trigger_event'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                        <input type="checkbox" name="is_active" value="1" <?= !empty($automation['is_active']) || !$isEdit ? 'checked' : '' ?> class="w-4 h-4 rounded">
                        Activa
                    </label>
                </div>
            </div>
        </div>

        <!-- Condiciones -->
        <div class="glass rounded-2xl p-5" x-data="conditionsBuilder(<?= htmlspecialchars(json_encode($conditions), ENT_QUOTES) ?>)">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold dark:text-white text-slate-900">Condiciones <span class="text-xs dark:text-slate-400 text-slate-500 font-normal">(todas deben cumplirse)</span></h3>
                <button type="button" @click="add()" class="px-3 py-1 rounded-lg glass text-xs dark:text-white text-slate-900">+ Condicion</button>
            </div>
            <template x-for="(c, i) in items" :key="i">
                <div class="grid grid-cols-12 gap-2 mb-2">
                    <select :name="`conditions[${i}][type]`" x-model="c.type" class="col-span-5 px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <option value="">Tipo</option>
                        <?php foreach ($conditionTypes as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" :name="`conditions[${i}][value]`" x-model="c.value" placeholder="valor" class="col-span-6 px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                    <button type="button" @click="remove(i)" class="col-span-1 text-red-500 hover:bg-red-500/10 rounded-lg">&times;</button>
                </div>
            </template>
            <p x-show="items.length === 0" class="text-xs dark:text-slate-500 text-slate-400 italic">Sin condiciones (siempre se ejecuta cuando ocurre el disparador).</p>
        </div>

        <!-- Acciones -->
        <div class="glass rounded-2xl p-5" x-data="actionsBuilder(<?= htmlspecialchars(json_encode($actions), ENT_QUOTES) ?>)">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold dark:text-white text-slate-900">Acciones <span class="text-xs dark:text-slate-400 text-slate-500 font-normal">(secuenciales)</span></h3>
                <button type="button" @click="add()" class="px-3 py-1 rounded-lg glass text-xs dark:text-white text-slate-900">+ Accion</button>
            </div>
            <template x-for="(a, i) in items" :key="i">
                <div class="border-l-4 border-primary/40 pl-3 mb-3">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-mono text-xs dark:text-cyan-300 text-cyan-700" x-text="(i+1)+'.'"></span>
                        <select :name="`actions[${i}][type]`" x-model="a.type" class="flex-1 px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                            <option value="">Selecciona accion</option>
                            <?php foreach ($actionTypes as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" @click="remove(i)" class="text-red-500 hover:bg-red-500/10 rounded-lg px-2">&times;</button>
                    </div>

                    <!-- Params dinamicos -->
                    <div x-show="a.type === 'send_whatsapp' || a.type === 'run_ai_reply'">
                        <textarea :name="`actions[${i}][params][message]`" x-model="a.params.message" rows="2" placeholder="Mensaje. Variables: {{contact_phone}} {{first_name}}"
                                  class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm"></textarea>
                    </div>

                    <div x-show="a.type === 'send_email'" class="space-y-2">
                        <input type="text" :name="`actions[${i}][params][to]`" x-model="a.params.to" placeholder="Destinatario o {{contact_email}}" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <input type="text" :name="`actions[${i}][params][subject]`" x-model="a.params.subject" placeholder="Asunto" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <textarea :name="`actions[${i}][params][body]`" x-model="a.params.body" rows="3" placeholder="Cuerpo del email" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm"></textarea>
                    </div>

                    <div x-show="a.type === 'add_tag'">
                        <input type="text" :name="`actions[${i}][params][tag]`" x-model="a.params.tag" placeholder="Nombre de etiqueta" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                    </div>

                    <div x-show="a.type === 'assign_agent' || a.type === 'notify'">
                        <select :name="`actions[${i}][params][user_id]`" x-model="a.params.user_id" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                            <option value="">Selecciona usuario</option>
                            <?php foreach ($agents as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <template x-if="a.type === 'notify'">
                            <div class="space-y-2 mt-2">
                                <input type="text" :name="`actions[${i}][params][title]`" x-model="a.params.title" placeholder="Titulo notificacion" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                                <input type="text" :name="`actions[${i}][params][body]`" x-model="a.params.body" placeholder="Mensaje" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                            </div>
                        </template>
                    </div>

                    <div x-show="a.type === 'change_lead_stage'">
                        <select :name="`actions[${i}][params][stage_id]`" x-model="a.params.stage_id" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                            <option value="">Selecciona etapa</option>
                            <?php foreach ($stages as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div x-show="a.type === 'create_ticket'" class="space-y-2">
                        <input type="text" :name="`actions[${i}][params][subject]`" x-model="a.params.subject" placeholder="Asunto" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <select :name="`actions[${i}][params][priority]`" x-model="a.params.priority" class="w-full px-2 py-1.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="critical">Critica</option>
                            <option value="low">Baja</option>
                        </select>
                    </div>
                </div>
            </template>
            <p x-show="items.length === 0" class="text-xs dark:text-slate-500 text-slate-400 italic">Sin acciones aun. Agrega al menos una.</p>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('/automations') ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
                <?= $isEdit ? 'Guardar cambios' : 'Crear automatizacion' ?>
            </button>
        </div>
    </div>

    <!-- Logs (en edit) -->
    <div class="space-y-4">
        <?php if ($isEdit && !empty($logs)): ?>
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Ultimas ejecuciones</h3>
            <div class="space-y-2 text-sm max-h-96 overflow-y-auto">
                <?php foreach ($logs as $l):
                    $cls = match ($l['status']) {
                        'success' => 'bg-emerald-500/20 text-emerald-300',
                        'failed'  => 'bg-red-500/20 text-red-300',
                        default   => 'bg-slate-500/20 text-slate-300',
                    };
                ?>
                <div class="flex items-center justify-between gap-2">
                    <span class="px-2 py-0.5 text-xs rounded-full <?= $cls ?>"><?= e($l['status']) ?></span>
                    <span class="text-xs dark:text-slate-400 text-slate-500"><?= time_ago((string) $l['created_at']) ?></span>
                </div>
                <?php if (!empty($l['error_message'])): ?>
                <div class="text-xs text-red-400 truncate"><?= e((string) $l['error_message']) ?></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-2">💡 Ejemplos</h3>
            <div class="space-y-3 text-xs dark:text-slate-300 text-slate-700">
                <div>
                    <div class="font-semibold dark:text-white text-slate-900 mb-1">Auto-respuesta de bienvenida</div>
                    <p>Cuando: Mensaje recibido<br>Si: Mensaje contiene "hola"<br>Hacer: Enviar WhatsApp con saludo personalizado</p>
                </div>
                <div>
                    <div class="font-semibold dark:text-white text-slate-900 mb-1">Lead caliente</div>
                    <p>Cuando: Lead cambia de etapa<br>Si: Score IA >= 70<br>Hacer: Notificar supervisor + Crear tarea</p>
                </div>
                <div>
                    <div class="font-semibold dark:text-white text-slate-900 mb-1">Fuera de horario</div>
                    <p>Cuando: Mensaje recibido<br>Si: Fuera de horario laboral<br>Hacer: Enviar mensaje "te respondemos manana"</p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function conditionsBuilder(initial) {
    return {
        items: initial && initial.length ? initial.map(x => ({ type: x.type || '', value: x.value || '' })) : [],
        add()    { this.items.push({ type: '', value: '' }); },
        remove(i){ this.items.splice(i, 1); },
    };
}
function actionsBuilder(initial) {
    return {
        items: initial && initial.length ? initial.map(x => ({ type: x.type || '', params: x.params || {} })) : [],
        add()    { this.items.push({ type: '', params: {} }); },
        remove(i){ this.items.splice(i, 1); },
    };
}
</script>

<?php \App\Core\View::stop(); ?>
