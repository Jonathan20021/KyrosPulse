<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$agents = $agents ?? [];
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'IA y entrenamiento', 'subtitle' => 'Configura el asistente y construye su base de conocimiento.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<div class="grid lg:grid-cols-2 gap-4 mb-4">
    <form action="<?= url('/settings/ai') ?>" method="POST" class="glass rounded-2xl p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Asistente IA</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre del asistente</label>
                <input type="text" name="ai_assistant_name" value="<?= e((string) ($tenant['ai_assistant_name'] ?? 'Asistente IA')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Tono de comunicacion</label>
                <input type="text" name="ai_tone" value="<?= e((string) ($tenant['ai_tone'] ?? 'profesional, cercano y claro')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <label class="flex items-center gap-2 dark:text-slate-300 text-slate-700">
                <input type="checkbox" name="ai_enabled" value="1" <?= !empty($tenant['ai_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                Activar bot de IA en mensajes entrantes
            </label>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje de bienvenida</label>
                <textarea name="welcome_message" rows="3" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['welcome_message'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje fuera de horario</label>
                <textarea name="out_of_hours_msg" rows="2" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['out_of_hours_msg'] ?? '')) ?></textarea>
            </div>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar</button>
        </div>
    </form>

    <form action="<?= url('/settings/ai/knowledge') ?>" method="POST" class="glass rounded-2xl p-5">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Agregar a base de conocimiento</h3>
        <p class="text-xs dark:text-slate-400 text-slate-500 mb-3">Estos articulos se inyectan en el contexto del asistente IA al responder.</p>
        <div class="space-y-3">
            <input type="text" name="category" required placeholder="Categoria: empresa, productos, faq, politicas..."
                   class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            <input type="text" name="title" required placeholder="Titulo del articulo"
                   class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            <textarea name="content" rows="6" required placeholder="Contenido del articulo, FAQ, politica..."
                      class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Agregar</button>
        </div>
    </form>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <form action="<?= url('/settings/ai/agents') ?>" method="POST" class="glass rounded-2xl p-5 lg:col-span-2">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Nuevo agente IA</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
                <input type="text" name="name" required placeholder="Agente de ventas, soporte, reservas..."
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Rol</label>
                <input type="text" name="role" placeholder="Toma ordenes, califica leads, atiende soporte"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Objetivo</label>
                <textarea name="objective" rows="2" placeholder="Que debe lograr este agente en cada conversacion"
                          class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Instrucciones operativas</label>
                <textarea name="instructions" rows="5" placeholder="Reglas, flujo de venta, cuando transferir a humano, datos que debe pedir, politicas..."
                          class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Tono</label>
                <input type="text" name="tone" value="profesional, claro y orientado a vender"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Modelo opcional</label>
                <input type="text" name="model" placeholder="<?= e((string) ($tenant['claude_model'] ?? '')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                <input type="checkbox" name="auto_reply_enabled" value="1" class="w-4 h-4 rounded">
                Responder automaticamente
            </label>
            <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                <input type="checkbox" name="is_default" value="1" <?= empty($agents) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                Usar como agente principal
            </label>
        </div>
        <button type="submit" class="mt-4 px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear agente</button>
    </form>

    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Agentes activos</h3>
        <?php if (empty($agents)): ?>
            <p class="text-sm dark:text-slate-400 text-slate-500">Crea un agente para activar respuestas automaticas por WhatsApp.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($agents as $a): ?>
                <div class="p-3 rounded-xl dark:bg-white/5 bg-slate-50 border dark:border-white/5 border-slate-200">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold dark:text-white text-slate-900"><?= e($a['name']) ?></div>
                            <div class="text-xs dark:text-slate-400 text-slate-500"><?= e((string) ($a['role'] ?? '')) ?></div>
                        </div>
                        <?php if (!empty($a['is_default'])): ?><span class="badge badge-cyan">Principal</span><?php endif; ?>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <form action="<?= url('/settings/ai/agents/' . $a['id'] . '/toggle') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button class="px-3 py-1.5 rounded-lg text-xs <?= !empty($a['auto_reply_enabled']) ? 'bg-emerald-500/20 text-emerald-300' : 'glass dark:text-slate-300 text-slate-600' ?>">
                                <?= !empty($a['auto_reply_enabled']) ? 'Auto ON' : 'Auto OFF' ?>
                            </button>
                        </form>
                        <form action="<?= url('/settings/ai/agents/' . $a['id']) ?>" method="POST" onsubmit="return confirm('Eliminar agente?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="px-3 py-1.5 rounded-lg text-xs text-red-500 hover:bg-red-500/10">Eliminar</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="glass rounded-2xl p-5">
    <h3 class="font-bold dark:text-white text-slate-900 mb-3">Base de conocimiento (<?= count($knowledge) ?>)</h3>
    <?php if (empty($knowledge)): ?>
    <p class="text-sm dark:text-slate-400 text-slate-500">Aun no hay articulos. Agrega arriba para entrenar al asistente.</p>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($knowledge as $k): ?>
        <div class="p-3 rounded-xl dark:bg-white/5 bg-slate-50 border dark:border-white/5 border-slate-200">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div class="min-w-0">
                    <span class="text-xs uppercase tracking-wider dark:text-cyan-400 text-cyan-600"><?= e($k['category']) ?></span>
                    <div class="font-semibold dark:text-white text-slate-900"><?= e($k['title']) ?></div>
                </div>
                <form action="<?= url('/settings/ai/knowledge/' . $k['id']) ?>" method="POST" onsubmit="return confirm('Eliminar?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="text-red-500 hover:bg-red-500/10 rounded p-1">&times;</button>
                </form>
            </div>
            <p class="text-sm dark:text-slate-300 text-slate-700 whitespace-pre-line"><?= e((string) $k['content']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php \App\Core\View::stop(); ?>
