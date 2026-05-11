<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$agents = $agents ?? [];
$kpi    = $kpi    ?? ['handled'=>0,'sent_messages'=>0,'transferred'=>0,'sales_closed'=>0,'tokens_in'=>0,'tokens_out'=>0];

$categories = [
    'generic'     => ['Generico',     '🤖'],
    'sales'       => ['Ventas',       '💰'],
    'support'     => ['Soporte',      '🎧'],
    'scheduling'  => ['Agendamiento', '📅'],
    'collections' => ['Cobros',       '💳'],
    'onboarding'  => ['Onboarding',   '🚀'],
    'retention'   => ['Retencion',    '🛟'],
];
$days = [
    'monday'    => 'Lun',
    'tuesday'   => 'Mar',
    'wednesday' => 'Mie',
    'thursday'  => 'Jue',
    'friday'    => 'Vie',
    'saturday'  => 'Sab',
    'sunday'    => 'Dom',
];

$decode = function ($v) {
    if (is_array($v)) return $v;
    if (!is_string($v) || $v === '') return [];
    $d = json_decode($v, true);
    return is_array($d) ? $d : [];
};

$autopilotOn = !empty($tenant['ai_force_all']);
?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php \App\Core\View::include('settings._partials.header', [
    'crumb'    => 'Inteligencia',
    'title'    => 'IA y agentes',
    'subtitle' => 'Crea multiples agentes IA especializados que atienden por ti.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="set-flash set-flash-success"><span>✓</span><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="set-flash set-flash-error"><span>⚠</span><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<section class="set-section set-autopilot <?= $autopilotOn ? 'is-on' : '' ?>">
    <div class="set-autopilot-glow"></div>
    <div class="set-autopilot-body">
        <div class="set-autopilot-info">
            <div class="set-autopilot-icon"><?= $autopilotOn ? '🤖' : '⚡' ?></div>
            <div>
                <div class="set-autopilot-head">
                    <h3 class="set-autopilot-title">Autopilot Total</h3>
                    <?php if ($autopilotOn): ?>
                    <span class="set-badge" style="background: rgba(16,185,129,.18); color:#10B981;">
                        <span class="set-int-dot" style="background: #10B981;"></span> Activo
                    </span>
                    <?php else: ?>
                    <span class="set-badge" style="background: rgba(148,163,184,.15); color:#94A3B8;">○ Inactivo</span>
                    <?php endif; ?>
                </div>
                <p class="set-autopilot-desc">
                    <?php if ($autopilotOn): ?>
                        La IA esta respondiendo TODOS los mensajes entrantes automaticamente. Cuando un humano responde manualmente, la IA se pausa 5 minutos en ese chat y luego retoma.
                    <?php else: ?>
                        Activa este modo para que la IA responda <strong>todos</strong> los mensajes entrantes sin tener que activar el bot manualmente en cada conversacion.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <form action="<?= url('/settings/ai/autopilot') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="enable" value="<?= $autopilotOn ? 0 : 1 ?>">
            <button type="submit"
                    onclick="<?= $autopilotOn ? "return confirm('Apagar Autopilot Total? La IA dejara de responder automaticamente en chats nuevos.')" : "return true" ?>"
                    class="set-btn <?= $autopilotOn ? 'set-btn-danger' : 'set-btn-primary' ?>">
                <?= $autopilotOn ? '⏸ Apagar Autopilot' : '▶ Activar Autopilot Total' ?>
            </button>
        </form>
    </div>

    <?php if ($autopilotOn): ?>
    <div class="set-autopilot-bullets">
        <div><span class="set-check-mark">✓</span> Responde mensajes nuevos</div>
        <div><span class="set-check-mark">✓</span> Atiende chats reabiertos</div>
        <div><span class="set-check-mark">✓</span> Respeta pausa manual del operador</div>
    </div>
    <?php endif; ?>
</section>

<?php if (!empty($aiSummary) && !empty($aiSummary['source'])):
    $isGlobal = $aiSummary['source'] === 'global';
    $pct = (int) ($aiSummary['pct'] ?? 0);
    $barColor = $pct >= 90 ? '#F43F5E' : ($pct >= 70 ? '#F59E0B' : '#10B981');
?>
<section class="set-section">
    <div class="set-section-head">
        <div>
            <h2 class="set-section-title"><span><?= $aiSummary['provider'] === 'openai' ? '🟢' : '🟣' ?></span> <?= e((string) $aiSummary['display_name']) ?></h2>
            <p class="set-section-desc"><code class="set-mono-xs"><?= e((string) ($aiSummary['model'] ?? '')) ?></code></p>
        </div>
        <a href="<?= e(url('/ai/usage')) ?>" class="set-btn set-btn-ghost set-btn-sm">Ver dashboard →</a>
    </div>

    <?php if ($isGlobal): ?>
    <span class="set-badge" style="background: rgba(6,182,212,.15); color: #06B6D4;">🏢 Provista por el SaaS</span>
    <?php else: ?>
    <span class="set-badge" style="background: rgba(16,185,129,.15); color: #10B981;">🔑 Tu propia API key</span>
    <?php endif; ?>

    <?php
        $usdBudget   = $aiSummary['usd_budget']    ?? null;
        $usdUsed     = (float) ($aiSummary['usd_used'] ?? 0);
        $tokenQuota  = $aiSummary['token_quota']   ?? null;
        $tokensUsed  = (int) ($aiSummary['tokens_used'] ?? 0);
    ?>
    <div class="set-quota" style="margin-top: 12px;">
        <?php if ($isGlobal && $usdBudget !== null && (float) $usdBudget > 0): ?>
            <div class="set-quota-head">
                <span class="set-quota-value">$<?= number_format($usdUsed, 2) ?> / $<?= number_format((float) $usdBudget, 2) ?></span>
                <span class="set-quota-pct" style="color: <?= $barColor ?>;"><?= $pct ?>%</span>
            </div>
            <div class="set-progress"><div class="set-progress-bar" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div></div>
            <p class="set-help"><?= number_format($tokensUsed) ?> tokens consumidos</p>
            <?php if ($pct >= 90): ?>
            <p class="set-help" style="color: #F43F5E;">⚠ Cerca de tu budget mensual. Aumenta el limite o agrega tu propia API key.</p>
            <?php endif; ?>
        <?php elseif ($isGlobal && $tokenQuota !== null && $tokenQuota > 0): ?>
            <div class="set-quota-head">
                <span class="set-quota-value"><?= number_format($tokensUsed) ?> / <?= number_format((int) $tokenQuota) ?> tokens</span>
                <span class="set-quota-pct" style="color: <?= $barColor ?>;"><?= $pct ?>%</span>
            </div>
            <div class="set-progress"><div class="set-progress-bar" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div></div>
        <?php elseif ($isGlobal): ?>
            <div class="set-quota-head">
                <span class="set-quota-value">$<?= number_format($usdUsed, 2) ?></span>
                <span class="set-help-inline">· sin tope</span>
            </div>
            <p class="set-help"><?= number_format($tokensUsed) ?> tokens · configura un budget para evitar gastos descontrolados.</p>
        <?php else: ?>
            <p class="set-help">Usas tu propia API key — el costo va directo a tu cuenta del proveedor. <strong>Sin limite</strong> impuesto por el SaaS, pero igual rastreamos uso y ROI.</p>
        <?php endif; ?>
    </div>
</section>
<?php elseif (!empty($aiSummary) && empty($aiSummary['source'])): ?>
<div class="set-notice set-notice-warning">
    <div class="set-notice-icon">⚠</div>
    <div class="set-notice-body">
        <div class="set-notice-title">Sin IA disponible</div>
        <p class="set-notice-desc">No tienes API key propia ni un proveedor IA global asignado. Pide al administrador del SaaS que te asigne uno o agrega tu propia key en <a href="<?= url('/settings/integrations/core') ?>" class="set-link">Integraciones</a>.</p>
    </div>
</div>
<?php endif; ?>

<div class="set-kpi-grid set-kpi-grid-5">
    <?php
    foreach ([
        ['Conversaciones IA',  $kpi['handled'],       '💬'],
        ['Mensajes enviados',  $kpi['sent_messages'], '⚡'],
        ['Escalados',          $kpi['transferred'],   '🙋'],
        ['Ventas cerradas',    $kpi['sales_closed'],  '💰'],
        ['Tokens (in / out)',  number_format($kpi['tokens_in']) . ' / ' . number_format($kpi['tokens_out']), '🧮'],
    ] as [$label, $value, $icon]):
    ?>
    <div class="set-kpi">
        <div class="set-kpi-head">
            <span class="set-kpi-label"><?= e($label) ?></span>
            <span class="set-kpi-emoji"><?= $icon ?></span>
        </div>
        <div class="set-kpi-value"><?= e((string) $value) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="set-split-2">
    <form action="<?= url('/settings/ai') ?>" method="POST" class="set-section">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">
        <div class="set-section-head">
            <h2 class="set-section-title"><span>⚙️</span> Configuracion global IA</h2>
        </div>
        <div class="set-field">
            <label class="set-label">Nombre del asistente (visible al cliente)</label>
            <input type="text" name="ai_assistant_name" value="<?= e((string) ($tenant['ai_assistant_name'] ?? 'Asistente')) ?>" class="set-input">
        </div>
        <div class="set-field">
            <label class="set-label">Tono general</label>
            <input type="text" name="ai_tone" value="<?= e((string) ($tenant['ai_tone'] ?? 'profesional, cercano y claro')) ?>" class="set-input">
        </div>
        <div class="set-field">
            <label class="set-check">
                <input type="checkbox" name="ai_enabled" value="1" <?= !empty($tenant['ai_enabled']) ? 'checked' : '' ?>>
                <div class="set-check-body">
                    <div class="set-check-title">Activar bot IA en mensajes entrantes</div>
                    <div class="set-check-desc">Master switch global del bot.</div>
                </div>
            </label>
        </div>
        <div class="set-field">
            <label class="set-label">Mensaje de bienvenida</label>
            <textarea name="welcome_message" rows="2" class="set-textarea"><?= e((string) ($tenant['welcome_message'] ?? '')) ?></textarea>
        </div>
        <div class="set-field">
            <label class="set-label">Mensaje fuera de horario</label>
            <textarea name="out_of_hours_msg" rows="2" class="set-textarea"><?= e((string) ($tenant['out_of_hours_msg'] ?? '')) ?></textarea>
        </div>
        <div class="set-actions">
            <button type="submit" class="set-btn set-btn-primary">Guardar global</button>
        </div>
    </form>

    <div>
        <form action="<?= url('/settings/ai/knowledge') ?>" method="POST" class="set-section">
            <?= csrf_field() ?>
            <div class="set-section-head">
                <h2 class="set-section-title"><span>📚</span> Agregar a base de conocimiento</h2>
                <p class="set-section-desc">Articulos que TODOS los agentes IA pueden citar al cliente.</p>
            </div>
            <div class="set-field">
                <input type="text" name="category" required placeholder="Categoria: empresa, productos, faq..." class="set-input">
            </div>
            <div class="set-field">
                <input type="text" name="title" required placeholder="Titulo del articulo" class="set-input">
            </div>
            <div class="set-field">
                <textarea name="content" rows="5" required placeholder="Contenido del articulo, FAQ, politica..." class="set-textarea"></textarea>
            </div>
            <div class="set-actions">
                <button type="submit" class="set-btn set-btn-primary">Agregar</button>
            </div>
        </form>

        <form action="<?= url('/settings/ai/knowledge/upload') ?>" method="POST" enctype="multipart/form-data" class="set-section">
            <?= csrf_field() ?>
            <div class="set-section-head">
                <h2 class="set-section-title"><span>📎</span> Importar archivo (.txt / .md)</h2>
                <p class="set-section-desc">Max 512 KB. Markdown con encabezados <code>##</code> se importa por seccion.</p>
            </div>
            <div class="set-field-row cols-2 set-field">
                <input type="text" name="category" placeholder="Categoria" value="documento" class="set-input">
                <input type="file" name="kb_file" accept=".txt,.md,.markdown" required class="set-input">
            </div>
            <div class="set-actions">
                <button type="submit" class="set-btn set-btn-success">Importar archivo</button>
            </div>
        </form>
    </div>
</div>

<div class="set-actions-bar" style="display: flex; justify-content: space-between; align-items: center;">
    <h2 class="set-h2">Equipo de agentes IA</h2>
    <button onclick="document.getElementById('newAgentForm').scrollIntoView({behavior:'smooth'})" class="set-btn set-btn-primary">+ Nuevo agente</button>
</div>

<?php if (empty($agents)): ?>
<section class="set-section">
    <div class="set-empty">
        <div class="set-empty-icon">🤖</div>
        <h3 class="set-empty-title">Aun no tienes agentes IA</h3>
        <p class="set-empty-desc">Crea agentes especializados (ventas, soporte, agendamiento...) y la IA los enrutara automaticamente segun el mensaje del cliente.</p>
    </div>
</section>
<?php else: ?>
<div class="set-split-2" style="margin-bottom: 16px;">
    <?php foreach ($agents as $a):
        $cat = (string) ($a['category'] ?? 'generic');
        [$catLabel, $catIcon] = $categories[$cat] ?? $categories['generic'];
        $triggers = $decode($a['trigger_keywords'] ?? null);
        $transfers = $decode($a['transfer_keywords'] ?? null);
        $aChannels = $decode($a['channels'] ?? null);
        $hours = $decode($a['working_hours'] ?? null);
        $emoji = trim((string) ($a['avatar_emoji'] ?? '')) ?: $catIcon;
    ?>
    <details class="set-section set-agent-card">
        <summary class="set-agent-summary">
            <div class="set-agent-summary-body">
                <div class="set-agent-avatar"><?= e($emoji) ?></div>
                <div class="set-agent-info">
                    <div class="set-rule-head">
                        <span class="set-rule-name"><?= e($a['name']) ?></span>
                        <span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;"><?= e($catLabel) ?></span>
                        <?php if (!empty($a['is_default'])): ?><span class="set-badge" style="background: rgba(6,182,212,.15); color:#06B6D4;">Principal</span><?php endif; ?>
                        <?php if (!empty($a['auto_reply_enabled'])): ?>
                        <span class="set-badge" style="background: rgba(16,185,129,.15); color:#10B981;">● Auto</span>
                        <?php else: ?>
                        <span class="set-badge" style="background: rgba(148,163,184,.15); color:#475569;">Pausado</span>
                        <?php endif; ?>
                    </div>
                    <div class="set-rule-desc"><?= e((string) ($a['role'] ?? 'Sin rol definido')) ?></div>
                    <?php if (!empty($triggers)): ?>
                    <div class="set-tool-chips">
                        <?php foreach (array_slice($triggers, 0, 5) as $kw): ?>
                        <span class="set-tool-chip">#<?= e($kw) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($triggers) > 5): ?><span class="set-tool-more">+<?= count($triggers)-5 ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="set-agent-summary-actions">
                <form action="<?= url('/settings/ai/agents/' . $a['id'] . '/toggle') ?>" method="POST" style="display:inline;" onclick="event.stopPropagation()">
                    <?= csrf_field() ?>
                    <button class="set-btn set-btn-ghost set-btn-sm" title="<?= !empty($a['auto_reply_enabled']) ? 'Pausar auto-reply' : 'Activar auto-reply' ?>">
                        <?= !empty($a['auto_reply_enabled']) ? '⏸' : '▶' ?>
                    </button>
                </form>
            </div>
        </summary>

        <form action="<?= url('/settings/ai/agents/' . $a['id']) ?>" method="POST" class="set-agent-form">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <div class="set-field-row cols-2 set-field">
                <div>
                    <label class="set-label">Nombre</label>
                    <input type="text" name="name" value="<?= e($a['name']) ?>" required class="set-input">
                </div>
                <div>
                    <label class="set-label">Categoria</label>
                    <select name="category" class="set-select">
                        <?php foreach ($categories as $val => [$label, $i]): ?>
                        <option value="<?= e($val) ?>" <?= $cat === $val ? 'selected' : '' ?>><?= e($i . ' ' . $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="set-label">Rol interno</label>
                    <input type="text" name="role" value="<?= e((string) ($a['role'] ?? '')) ?>" placeholder="Vendedor consultivo, soporte tecnico..." class="set-input">
                </div>
                <div>
                    <label class="set-label">Avatar (emoji)</label>
                    <input type="text" name="avatar_emoji" value="<?= e((string) ($a['avatar_emoji'] ?? '')) ?>" placeholder="🤖 💰 🎧" maxlength="4" class="set-input">
                </div>
            </div>
            <div class="set-field">
                <label class="set-label">Objetivo</label>
                <textarea name="objective" rows="2" class="set-textarea"><?= e((string) ($a['objective'] ?? '')) ?></textarea>
            </div>
            <div class="set-field">
                <label class="set-label">Instrucciones operativas (manual del agente)</label>
                <textarea name="instructions" rows="6" placeholder="Reglas, flujo de venta, datos a pedir, politicas, ejemplos..." class="set-textarea"><?= e((string) ($a['instructions'] ?? '')) ?></textarea>
            </div>
            <div class="set-field-row cols-3 set-field">
                <div>
                    <label class="set-label">Tono</label>
                    <input type="text" name="tone" value="<?= e((string) ($a['tone'] ?? '')) ?>" class="set-input">
                </div>
                <div>
                    <label class="set-label">Modelo (opcional)</label>
                    <input type="text" name="model" value="<?= e((string) ($a['model'] ?? '')) ?>" placeholder="hereda del tenant" class="set-input">
                </div>
                <div>
                    <label class="set-label">Prioridad</label>
                    <input type="number" name="priority" value="<?= (int) ($a['priority'] ?? 100) ?>" min="1" max="999" class="set-input">
                </div>
            </div>
            <div class="set-field">
                <label class="set-label">Reintentos antes de escalar</label>
                <input type="number" name="max_retries" value="<?= (int) ($a['max_retries'] ?? 3) ?>" min="1" max="20" class="set-input">
            </div>
            <div class="set-field">
                <label class="set-label">Palabras clave que activan este agente (separa por coma)</label>
                <textarea name="trigger_keywords" rows="2" placeholder="precio, comprar, plan, cotizacion, factura..." class="set-textarea"><?= e(implode(', ', $triggers)) ?></textarea>
            </div>
            <div class="set-field">
                <label class="set-label">Palabras que disparan transferencia a humano</label>
                <textarea name="transfer_keywords" rows="2" placeholder="humano, agente real, hablar con persona, queja, demanda..." class="set-textarea"><?= e(implode(', ', $transfers)) ?></textarea>
            </div>

            <div class="set-field">
                <label class="set-label">Canales donde opera (vacio = todos)</label>
                <div class="set-chip-group">
                    <?php foreach (['whatsapp','email','webchat','instagram','facebook','telegram'] as $ch):
                        $checked = empty($aChannels) ? false : in_array($ch, $aChannels, true);
                    ?>
                    <label class="set-pill-check">
                        <input type="checkbox" name="channels[]" value="<?= e($ch) ?>" <?= $checked ? 'checked' : '' ?>>
                        <span><?= ucfirst($ch) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="set-field">
                <label class="set-label">Horario de operacion (vacio = 24/7)</label>
                <div class="set-hour-grid">
                    <?php foreach ($days as $key => $label):
                        $cfg = $hours[$key] ?? ['enabled'=>false,'start'=>'09:00','end'=>'18:00'];
                    ?>
                    <div class="set-hour-row">
                        <label class="set-hour-day">
                            <input type="checkbox" name="agent_hours[<?= $key ?>][enabled]" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                        <input type="time" name="agent_hours[<?= $key ?>][start]" value="<?= e((string) ($cfg['start'] ?? '09:00')) ?>" class="set-input set-input-xs">
                        <span class="set-sep">-</span>
                        <input type="time" name="agent_hours[<?= $key ?>][end]" value="<?= e((string) ($cfg['end'] ?? '18:00')) ?>" class="set-input set-input-xs">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="set-field-row cols-2 set-field">
                <label class="set-check">
                    <input type="checkbox" name="auto_reply_enabled" value="1" <?= !empty($a['auto_reply_enabled']) ? 'checked' : '' ?>>
                    <div class="set-check-body"><div class="set-check-title">Responder automaticamente</div></div>
                </label>
                <label class="set-check">
                    <input type="checkbox" name="is_default" value="1" <?= !empty($a['is_default']) ? 'checked' : '' ?>>
                    <div class="set-check-body"><div class="set-check-title">Agente principal (fallback)</div></div>
                </label>
            </div>

            <div class="set-actions">
                <a href="<?= url('/settings/ai/agents/' . $a['id'] . '/skills') ?>" class="set-btn set-btn-ghost">🧠 Skills</a>
                <form action="<?= url('/settings/ai/agents/' . $a['id'] . '/duplicate') ?>" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="set-btn set-btn-ghost">Duplicar</button>
                </form>
                <form action="<?= url('/settings/ai/agents/' . $a['id']) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar este agente?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="set-btn set-btn-danger">Eliminar</button>
                </form>
                <button type="submit" class="set-btn set-btn-primary">Guardar cambios</button>
            </div>
        </form>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form id="newAgentForm" action="<?= url('/settings/ai/agents') ?>" method="POST" class="set-section">
    <?= csrf_field() ?>
    <div class="set-section-head">
        <h2 class="set-section-title"><span>➕</span> Crear nuevo agente IA</h2>
    </div>
    <div class="set-field-row cols-2 set-field">
        <div>
            <label class="set-label">Nombre</label>
            <input type="text" name="name" required placeholder="Vendedor IA, Soporte IA, Agendador..." class="set-input">
        </div>
        <div>
            <label class="set-label">Categoria</label>
            <select name="category" class="set-select">
                <?php foreach ($categories as $val => [$label, $i]): ?>
                <option value="<?= e($val) ?>"><?= e($i . ' ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="set-label">Rol</label>
            <input type="text" name="role" placeholder="Vendedor consultivo de planes..." class="set-input">
        </div>
        <div>
            <label class="set-label">Avatar (emoji)</label>
            <input type="text" name="avatar_emoji" value="🤖" maxlength="4" class="set-input">
        </div>
    </div>
    <div class="set-field">
        <label class="set-label">Objetivo</label>
        <textarea name="objective" rows="2" placeholder="Que debe lograr este agente" class="set-textarea"></textarea>
    </div>
    <div class="set-field">
        <label class="set-label">Instrucciones operativas</label>
        <textarea name="instructions" rows="6" placeholder="Reglas, flujo, datos que pedir, cuando transferir, ejemplos..." class="set-textarea"></textarea>
    </div>
    <div class="set-field-row cols-2 set-field">
        <div>
            <label class="set-label">Tono</label>
            <input type="text" name="tone" value="profesional, claro y orientado a vender" class="set-input">
        </div>
        <div>
            <label class="set-label">Prioridad</label>
            <input type="number" name="priority" value="100" min="1" max="999" class="set-input">
        </div>
    </div>
    <div class="set-field">
        <label class="set-label">Palabras clave que activan este agente (coma separadas)</label>
        <input type="text" name="trigger_keywords" placeholder="precio, comprar, plan, factura, cotizacion" class="set-input">
    </div>
    <div class="set-field-row cols-2 set-field">
        <label class="set-check">
            <input type="checkbox" name="auto_reply_enabled" value="1" checked>
            <div class="set-check-body"><div class="set-check-title">Responder automaticamente</div></div>
        </label>
        <label class="set-check">
            <input type="checkbox" name="is_default" value="1" <?= empty($agents) ? 'checked' : '' ?>>
            <div class="set-check-body"><div class="set-check-title">Agente principal (recibe todo lo no enrutado)</div></div>
        </label>
    </div>
    <div class="set-actions">
        <button type="submit" class="set-btn set-btn-primary">Crear agente</button>
    </div>
</form>

<section class="set-section">
    <div class="set-section-head">
        <h2 class="set-section-title"><span>📚</span> Base de conocimiento <span class="set-count">(<?= count($knowledge) ?>)</span></h2>
    </div>
    <?php if (empty($knowledge)): ?>
    <div class="set-empty">
        <p class="set-empty-text">Aun no hay articulos. Agrega arriba para entrenar a TODOS los agentes.</p>
    </div>
    <?php else: ?>
    <ul class="set-kb-list">
        <?php foreach ($knowledge as $k): ?>
        <li class="set-kb-item">
            <div class="set-kb-head">
                <div>
                    <span class="set-badge" style="background: rgba(6,182,212,.15); color:#06B6D4;"><?= e($k['category']) ?></span>
                    <span class="set-kb-title"><?= e($k['title']) ?></span>
                </div>
                <form action="<?= url('/settings/ai/knowledge/' . $k['id']) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Eliminar?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="set-btn set-btn-danger set-btn-sm">×</button>
                </form>
            </div>
            <p class="set-kb-content"><?= e((string) $k['content']) ?></p>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php \App\Core\View::include('settings._tabs_end'); ?>
<?php \App\Core\View::stop(); ?>
