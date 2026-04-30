<?php
/** @var array $stats */
/** @var array $recentConversations */
/** @var array $agentActivity */
/** @var array $chartData */
/** @var array $heatmap */
/** @var array $stageDistribution */
/** @var array $sentiment */
/** @var array $activityFeed */
/** @var array $alerts */
/** @var array|null $tenantData */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$user = auth() ?? [];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
$currency = (string) ($tenantData['currency'] ?? 'USD');

// Metricas derivadas
$pipelineGoal = max(50000, (float) $stats['pipeline_value'] * 1.4);
$pipelineProgress = min(100, (int) round(($stats['pipeline_value'] / $pipelineGoal) * 100));
$conversionRate = $stats['leads_total'] > 0 ? (int) round(($stats['leads_won'] / $stats['leads_total']) * 100) : 0;
$satisfactionScore = 92; // placeholder, viene de tickets en otra metrica
?>

<!-- ============================================================================
     HEADER - Greeting + Live status + Actions
     ============================================================================ -->
<div class="mb-7 flex flex-wrap items-end justify-between gap-4 animate-fade-in">
    <div>
        <div class="flex items-center gap-2 mb-2">
            <span class="live-dot"></span>
            <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-text-tertiary);">En vivo · <?= date('l, d \\d\\e F Y') ?></span>
        </div>
        <h1 class="text-[28px] lg:text-[32px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.025em;">
            <?= $greeting ?>, <span class="gradient-text-aurora"><?= e($user['first_name'] ?? '') ?></span>
        </h1>
        <p class="text-sm mt-1.5" style="color: var(--color-text-tertiary);">Aqui esta el pulso de tu negocio en tiempo real.</p>
    </div>
    <div class="flex items-center gap-2">
        <div class="segmented hidden md:inline-flex">
            <button class="active">Hoy</button>
            <button>7d</button>
            <button>30d</button>
            <button>90d</button>
        </div>
        <a href="<?= url('/inbox') ?>" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            Bandeja
        </a>
        <a href="<?= url('/contacts/create') ?>" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo
        </a>
    </div>
</div>

<!-- ============================================================================
     ALERTS
     ============================================================================ -->
<?php if (!empty($alerts)): ?>
<div class="space-y-2 mb-6 animate-fade-in">
    <?php foreach ($alerts as $a):
        [$cls, $color, $iconPath] = match ($a['type']) {
            'warning' => ['rgba(245,158,11,0.06)', '#FBBF24', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            'info'    => ['rgba(6,182,212,0.06)',  '#22D3EE', 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            default   => ['rgba(16,185,129,0.06)', '#34D399', 'M5 13l4 4L19 7'],
        };
    ?>
    <div class="rounded-xl px-4 py-3 flex items-center gap-3 text-sm border" style="background: <?= $cls ?>; border-color: <?= $color ?>30; color: <?= $color ?>;">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $iconPath ?>"/></svg>
        <span class="flex-1"><?= e($a['msg']) ?></span>
        <button class="hover:opacity-70 transition" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================================
     KPI HERO - 4 Premium cards with animated counters + sparklines
     ============================================================================ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4 stagger-in">
    <?php
    $hero = [
        [
            'label'     => 'Contactos',
            'value'     => (int) $stats['contacts_total'],
            'fmt'       => 'number',
            'change'    => '+12.4%',
            'positive'  => true,
            'tone'      => 'violet',
            'color'     => '#7C3AED',
            'icon'      => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            'spark'     => [3, 5, 4, 7, 6, 8, 9, 7, 10, 12],
        ],
        [
            'label'     => 'Pipeline activo',
            'value'     => (float) $stats['pipeline_value'],
            'fmt'       => 'currency',
            'change'    => '+38.2%',
            'positive'  => true,
            'tone'      => 'emerald',
            'color'     => '#10B981',
            'icon'      => 'M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z',
            'spark'     => [2, 3, 5, 4, 6, 8, 7, 9, 11, 14],
        ],
        [
            'label'     => 'Leads abiertos',
            'value'     => (int) $stats['leads_open'],
            'fmt'       => 'number',
            'change'    => '+24.1%',
            'positive'  => true,
            'tone'      => 'cyan',
            'color'     => '#06B6D4',
            'icon'      => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
            'spark'     => [4, 6, 5, 8, 7, 6, 9, 8, 11, 10],
        ],
        [
            'label'     => 'Conv. abiertas',
            'value'     => (int) $stats['conv_open'],
            'fmt'       => 'number',
            'change'    => '+8.4%',
            'positive'  => true,
            'tone'      => 'amber',
            'color'     => '#F59E0B',
            'icon'      => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'spark'     => [5, 7, 6, 9, 8, 7, 6, 8, 7, 9],
        ],
    ];
    foreach ($hero as $i => $c):
        $max = max($c['spark']) ?: 1;
        $points = '';
        foreach ($c['spark'] as $idx => $v) {
            $x = $idx * (100 / (count($c['spark']) - 1));
            $y = 35 - ($v / $max) * 28;
            $points .= ($idx === 0 ? "M$x,$y" : " L$x,$y");
        }
        $gradId = 'gh' . $i;
        $valueDisplay = $c['fmt'] === 'currency' ? format_currency((float) $c['value'], $currency) : number_format((float) $c['value']);
    ?>
    <div class="kpi-card" data-tone="<?= $c['tone'] ?>">
        <div class="flex items-start justify-between mb-3 relative">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: <?= $c['color'] ?>15; color: <?= $c['color'] ?>;">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $c['icon'] ?>"/></svg>
            </div>
            <span class="badge <?= $c['positive'] ? 'badge-emerald' : 'badge-rose' ?> badge-dot">
                <?= $c['change'] ?>
            </span>
        </div>
        <div class="text-[11px] uppercase font-semibold tracking-[0.1em] mb-1.5" style="color: var(--color-text-tertiary);"><?= e($c['label']) ?></div>
        <div class="text-[26px] font-bold tracking-tight tabular-nums" style="color: var(--color-text-primary); letter-spacing: -0.02em;">
            <span class="kpi-counter" data-target="<?= (float) $c['value'] ?>" data-format="<?= $c['fmt'] ?>" data-currency="<?= e($currency) ?>"><?= e($valueDisplay) ?></span>
        </div>
        <svg class="w-full h-10 mt-3" viewBox="0 0 100 35" preserveAspectRatio="none">
            <defs>
                <linearGradient id="<?= $gradId ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="<?= $c['color'] ?>" stop-opacity="0.4"/>
                    <stop offset="100%" stop-color="<?= $c['color'] ?>" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path d="<?= $points ?> L100,35 L0,35 Z" fill="url(#<?= $gradId ?>)"/>
            <path d="<?= $points ?>" fill="none" stroke="<?= $c['color'] ?>" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================================================
     RING METRICS - 4 progress rings with goals
     ============================================================================ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php
    $rings = [
        ['Tasa de respuesta',       (int) $stats['response_rate'],     '%',  '#7C3AED', 'Mensajes resp. / recibidos'],
        ['Conversion de leads',     $conversionRate,                    '%',  '#10B981', 'Won / Total'],
        ['Meta de pipeline',        $pipelineProgress,                   '%',  '#06B6D4', 'Progreso vs objetivo'],
        ['Satisfaccion',            $satisfactionScore,                  '%',  '#F59E0B', 'Score CSAT'],
    ];
    foreach ($rings as $r):
        [$label, $val, $unit, $color, $sub] = $r;
        $val = max(0, min(100, $val));
    ?>
    <div class="surface p-5 hover-lift">
        <div class="flex items-center gap-4">
            <div class="ring-progress" style="--ring-progress: <?= $val ?>; --ring-color: <?= $color ?>; --ring-size: 64px; --ring-thickness: 6px;">
                <svg viewBox="0 0 100 100">
                    <circle class="ring-bg" cx="50" cy="50" r="40"/>
                    <circle class="ring-fg" cx="50" cy="50" r="40"/>
                </svg>
                <div class="ring-label" style="font-size: 0.875rem;"><?= $val ?><span class="text-xs" style="color: var(--color-text-tertiary);"><?= $unit ?></span></div>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-bold mb-0.5" style="color: var(--color-text-primary);"><?= e($label) ?></div>
                <div class="text-[11px]" style="color: var(--color-text-tertiary);"><?= e($sub) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================================================
     CHART + STAGE FUNNEL
     ============================================================================ -->
<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <!-- Big chart -->
    <div class="lg:col-span-2 surface p-6 relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 rounded-full opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,0.4), transparent 60%); filter: blur(40px);"></div>

        <div class="flex items-center justify-between mb-5 relative">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Actividad de mensajes</h3>
                    <span class="badge badge-primary">Live</span>
                </div>
                <p class="text-xs" style="color: var(--color-text-tertiary);">Volumen de los ultimos 14 dias</p>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="flex items-center gap-1.5 font-medium" style="color: var(--color-text-secondary);">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background: linear-gradient(135deg, #7C3AED, #A78BFA);"></span>Recibidos
                </span>
                <span class="flex items-center gap-1.5 font-medium" style="color: var(--color-text-secondary);">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background: linear-gradient(135deg, #06B6D4, #67E8F9);"></span>Enviados
                </span>
            </div>
        </div>
        <div class="h-72 relative">
            <canvas id="messagesChart"></canvas>
        </div>
    </div>

    <!-- Funnel -->
    <div class="surface p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Pipeline funnel</h3>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Distribucion por etapa</p>
            </div>
            <a href="<?= url('/leads') ?>" class="btn btn-ghost btn-sm">Ver →</a>
        </div>
        <?php if (empty($stageDistribution)): ?>
        <div class="text-center py-10">
            <div class="text-3xl mb-2 opacity-40">📊</div>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Aun no hay etapas configuradas.</p>
        </div>
        <?php else:
            $maxStage = max(array_map(fn($s) => (int) $s['total'], $stageDistribution)) ?: 1;
        ?>
        <div class="space-y-3">
            <?php foreach ($stageDistribution as $s):
                $pct = (int) round(((int) $s['total'] / $maxStage) * 100);
            ?>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: <?= e((string) $s['color']) ?>"></span>
                        <span class="text-xs font-semibold truncate" style="color: var(--color-text-primary);"><?= e((string) $s['name']) ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-xs flex-shrink-0">
                        <span class="font-mono font-bold" style="color: var(--color-text-primary);"><?= (int) $s['total'] ?></span>
                        <span style="color: var(--color-text-tertiary);"><?= format_currency((float) $s['value'], $currency) ?></span>
                    </div>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?= $pct ?>%; background: linear-gradient(90deg, <?= e((string) $s['color']) ?>, <?= e((string) $s['color']) ?>cc);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================================
     HEATMAP + TOP AGENTS + AI INSIGHTS
     ============================================================================ -->
<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <!-- Heatmap -->
    <div class="surface p-6 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Mapa de actividad</h3>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Mensajes por hora x dia (ultimos 30 dias)</p>
            </div>
            <div class="flex items-center gap-1.5 text-[10px]" style="color: var(--color-text-tertiary);">
                <span>Menos</span>
                <span class="w-3 h-3 rounded-sm" style="background: var(--color-bg-subtle);"></span>
                <span class="w-3 h-3 rounded-sm" style="background: rgba(124,58,237,0.18);"></span>
                <span class="w-3 h-3 rounded-sm" style="background: rgba(124,58,237,0.40);"></span>
                <span class="w-3 h-3 rounded-sm" style="background: rgba(124,58,237,0.65);"></span>
                <span class="w-3 h-3 rounded-sm" style="background: rgba(124,58,237,0.95);"></span>
                <span>Mas</span>
            </div>
        </div>
        <?php
            $dayLabels = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
            $maxHm = (int) ($heatmap['max'] ?? 1);
        ?>
        <div class="space-y-1">
            <?php foreach ($heatmap['grid'] as $d => $hours): ?>
            <div class="flex items-center gap-2">
                <div class="w-8 text-[10px] font-mono uppercase" style="color: var(--color-text-tertiary);"><?= $dayLabels[$d] ?></div>
                <div class="flex-1 grid gap-[3px]" style="grid-template-columns: repeat(24, minmax(0, 1fr));">
                    <?php for ($h = 0; $h < 24; $h++):
                        $val = (int) ($hours[$h] ?? 0);
                        $level = 0;
                        if ($val > 0) {
                            $ratio = $val / $maxHm;
                            $level = $ratio < 0.25 ? 1 : ($ratio < 0.5 ? 2 : ($ratio < 0.75 ? 3 : 4));
                        }
                    ?>
                    <div class="heatmap-cell" data-level="<?= $level ?>" title="<?= $dayLabels[$d] ?> <?= $h ?>:00 - <?= $val ?> mensajes"></div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="flex items-center gap-2 pt-2">
                <div class="w-8"></div>
                <div class="flex-1 grid gap-[3px] text-[9px] font-mono" style="grid-template-columns: repeat(24, minmax(0, 1fr)); color: var(--color-text-muted);">
                    <?php for ($h = 0; $h < 24; $h++): ?>
                    <div class="text-center"><?= $h % 6 === 0 ? $h . 'h' : '' ?></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights -->
    <div class="surface p-6 relative overflow-hidden scan-line" style="background: linear-gradient(135deg, rgba(124,58,237,0.04), rgba(6,182,212,0.04));">
        <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full" style="background: radial-gradient(circle, rgba(124,58,237,0.18), transparent 70%); filter: blur(20px);"></div>
        <div class="relative">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base" style="background: var(--gradient-primary); box-shadow: 0 4px 14px rgba(124,58,237,.3);">
                    <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-primary);">IA · Claude</span>
                    <div class="text-[15px] font-bold" style="color: var(--color-text-primary);">Insights del dia</div>
                </div>
            </div>

            <div class="space-y-3 mt-4">
                <?php if ($stats['hot_leads'] > 0): ?>
                <div class="rounded-lg p-3 border" style="background: rgba(244,63,94,0.06); border-color: rgba(244,63,94,0.2);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-base">🔥</span>
                        <span class="text-xs font-bold" style="color: #FB7185;"><?= $stats['hot_leads'] ?> leads calientes</span>
                    </div>
                    <p class="text-[11px]" style="color: var(--color-text-secondary);">Score IA &ge; 70. Atiende prioritariamente.</p>
                </div>
                <?php endif; ?>

                <?php if ($stats['conv_open'] > 0): ?>
                <div class="rounded-lg p-3 border" style="background: rgba(245,158,11,0.06); border-color: rgba(245,158,11,0.2);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-base">⚡</span>
                        <span class="text-xs font-bold" style="color: #FBBF24;"><?= $stats['conv_open'] ?> conversaciones</span>
                    </div>
                    <p class="text-[11px]" style="color: var(--color-text-secondary);">Pendientes de respuesta. SLA en riesgo.</p>
                </div>
                <?php endif; ?>

                <?php if ($stats['response_rate'] >= 80): ?>
                <div class="rounded-lg p-3 border" style="background: rgba(16,185,129,0.06); border-color: rgba(16,185,129,0.2);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-base">✨</span>
                        <span class="text-xs font-bold" style="color: #34D399;">Excelente respuesta</span>
                    </div>
                    <p class="text-[11px]" style="color: var(--color-text-secondary);"><?= $stats['response_rate'] ?>% de tasa de respuesta. Sigue asi.</p>
                </div>
                <?php endif; ?>

                <a href="<?= url('/leads') ?>" class="btn btn-secondary btn-sm w-full justify-center">Ver pipeline completo</a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================
     RECENT CONVERSATIONS + TOP AGENTS + ACTIVITY FEED
     ============================================================================ -->
<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <!-- Recent conversations -->
    <div class="lg:col-span-2 surface">
        <div class="flex items-center justify-between p-5 border-b" style="border-color: var(--color-border-subtle);">
            <div>
                <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Conversaciones recientes</h3>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Ultimas interacciones del equipo</p>
            </div>
            <a href="<?= url('/inbox') ?>" class="btn btn-ghost btn-sm">
                Ver todas
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if (empty($recentConversations)): ?>
        <div class="empty-state-pro">
            <div class="empty-icon">💬</div>
            <h4 class="font-bold mb-1" style="color: var(--color-text-primary);">Sin conversaciones aun</h4>
            <p class="text-sm mb-4" style="color: var(--color-text-tertiary);">Conecta tu primer numero (Wasapi o WhatsApp Cloud API) para recibir mensajes en tiempo real.</p>
            <a href="<?= url('/settings/channels') ?>" class="btn btn-primary btn-sm">Conectar WhatsApp</a>
        </div>
        <?php else: ?>
        <div>
            <?php foreach ($recentConversations as $c):
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['phone'] ?? 'Sin nombre');
                $statusBadge = match ($c['status']) {
                    'new'      => 'badge-cyan',
                    'open'     => 'badge-emerald',
                    'pending'  => 'badge-amber',
                    'resolved' => 'badge-slate',
                    default    => 'badge-slate',
                };
                $statusLabel = match ($c['status']) {
                    'new'      => 'Nueva',
                    'open'     => 'Abierta',
                    'pending'  => 'Pendiente',
                    'resolved' => 'Resuelta',
                    default    => $c['status'],
                };
            ?>
            <a href="<?= url('/inbox') ?>" class="flex items-center gap-3 px-5 py-3.5 transition border-b last:border-b-0 hover:bg-[color:var(--color-bg-subtle)]" style="border-color: var(--color-border-subtle);">
                <div class="relative flex-shrink-0">
                    <div class="avatar avatar-md"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 2px solid var(--color-bg-surface);"></span>
                    <?php if ((int) ($c['unread_count'] ?? 0) > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= (int) $c['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="font-semibold text-sm truncate" style="color: var(--color-text-primary);"><?= e($name) ?></span>
                        <span class="badge <?= $statusBadge ?>"><?= e($statusLabel) ?></span>
                    </div>
                    <div class="text-xs truncate" style="color: var(--color-text-tertiary);"><?= e((string) ($c['last_message'] ?? '')) ?></div>
                </div>
                <div class="text-[11px] flex-shrink-0 font-mono" style="color: var(--color-text-muted);"><?= e(time_ago((string) ($c['last_message_at'] ?? $c['updated_at']))) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Activity feed (timeline) -->
    <div class="surface p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Actividad del equipo</h3>
            <span class="badge badge-primary badge-dot">Live</span>
        </div>
        <?php if (empty($activityFeed)): ?>
        <div class="text-center py-8">
            <div class="text-3xl mb-2 opacity-40">📋</div>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Sin actividad reciente.</p>
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($activityFeed as $f):
                $action = match ($f['action']) {
                    'create' => 'creo',
                    'update' => 'actualizo',
                    'delete' => 'elimino',
                    default  => $f['action'],
                };
                $entity = match ($f['entity_type']) {
                    'contact'      => 'un contacto',
                    'lead'         => 'un lead',
                    'ticket'       => 'un ticket',
                    'task'         => 'una tarea',
                    'campaign'     => 'una campana',
                    'automation'   => 'una automatizacion',
                    'conversation' => 'una conversacion',
                    'message'      => 'un mensaje',
                    default        => $f['entity_type'],
                };
                $userName = trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? '')) ?: 'Sistema';
            ?>
            <div class="timeline-item">
                <div class="text-xs leading-relaxed" style="color: var(--color-text-secondary);">
                    <strong style="color: var(--color-text-primary);"><?= e($userName) ?></strong>
                    <?= e($action) ?>
                    <?= e($entity) ?>
                </div>
                <div class="text-[10px] mt-0.5" style="color: var(--color-text-muted);"><?= e(time_ago((string) $f['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================================
     TOP AGENTS + QUICK ACTIONS
     ============================================================================ -->
<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="surface p-6 lg:col-span-2">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Ranking de agentes</h3>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Performance de los ultimos 7 dias</p>
            </div>
            <button class="btn btn-ghost btn-sm">Ver todos →</button>
        </div>

        <?php if (empty($agentActivity)): ?>
        <div class="text-center py-10">
            <div class="text-3xl mb-2 opacity-40">👥</div>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Aun no hay actividad.</p>
        </div>
        <?php else:
            $maxMsg = max(array_map(fn($a) => (int) $a['messages_sent'], $agentActivity)) ?: 1;
        ?>
        <div class="space-y-3">
            <?php foreach ($agentActivity as $i => $a):
                $rank = $i + 1;
                $rankColor = match ($rank) { 1 => '#FBBF24', 2 => '#94A3B8', 3 => '#FB923C', default => 'var(--color-text-muted)' };
                $rankIcon = match ($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => '#' . $rank };
                $pct = (int) round(((int) $a['messages_sent'] / $maxMsg) * 100);
            ?>
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-7 text-center text-base" style="color: <?= $rankColor ?>;"><?= $rankIcon ?></div>
                <div class="avatar avatar-md flex-shrink-0 relative">
                    <?= e(strtoupper(mb_substr((string) $a['first_name'], 0, 1) . mb_substr((string) $a['last_name'], 0, 1))) ?>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 2px solid var(--color-bg-surface);"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-sm font-semibold truncate" style="color: var(--color-text-primary);"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div class="flex items-center gap-3 text-xs flex-shrink-0">
                            <span style="color: var(--color-text-tertiary);"><?= (int) $a['conversations'] ?> conv.</span>
                            <span class="font-mono font-bold tabular-nums" style="color: var(--color-text-primary);"><?= (int) $a['messages_sent'] ?></span>
                        </div>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?= $pct ?>%;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick actions premium -->
    <div class="surface p-6">
        <h3 class="font-bold text-[15px] mb-4" style="color: var(--color-text-primary);">Acciones rapidas</h3>
        <div class="space-y-2">
            <?php
            $actions = [
                ['/contacts/create',  'Crear contacto',   'M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M19 8v6m3-3h-6M8.5 7a4 4 0 11-8 0 4 4 0 018 0z', '#7C3AED'],
                ['/leads/create',     'Nuevo lead',       'M12 4v16m8-8H4', '#10B981'],
                ['/tickets/create',   'Crear ticket',     'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', '#06B6D4'],
                ['/tasks',            'Nueva tarea',      'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', '#F59E0B'],
                ['/campaigns/create', 'Lanzar campana',   'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', '#F43F5E'],
            ];
            foreach ($actions as $a): ?>
            <a href="<?= url($a[0]) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition group hover:bg-[color:var(--color-bg-hover)]" style="background: var(--color-bg-subtle); color: var(--color-text-secondary); border: 1px solid var(--color-border-subtle);">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center transition group-hover:scale-110" style="background: <?= $a[3] ?>15; color: <?= $a[3] ?>;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $a[2] ?>"/></svg>
                </div>
                <span class="flex-1 font-medium"><?= e($a[1]) ?></span>
                <svg class="w-3.5 h-3.5 opacity-30 group-hover:opacity-100 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 pt-5 border-t" style="border-color: var(--color-border-subtle);">
            <div class="text-[10px] uppercase font-semibold tracking-wider mb-2" style="color: var(--color-text-tertiary);">Atajos</div>
            <div class="flex flex-wrap gap-1.5 text-[10px]" style="color: var(--color-text-tertiary);">
                <span><span class="kbd">⌘K</span> buscar</span>
                <span><span class="kbd">G</span> + <span class="kbd">D</span> dashboard</span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================
     SCRIPTS
     ============================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ----- Counter animation -----
    document.querySelectorAll('.kpi-counter').forEach(el => {
        const target = parseFloat(el.dataset.target) || 0;
        const fmt = el.dataset.format || 'number';
        const currency = el.dataset.currency || 'USD';
        const duration = 1400;
        const start = performance.now();

        function format(v) {
            if (fmt === 'currency') {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency, maximumFractionDigits: 0 }).format(v);
            }
            return new Intl.NumberFormat('en-US').format(Math.round(v));
        }

        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = format(target * eased);
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = format(target);
        }
        requestAnimationFrame(tick);
    });

    // ----- Messages chart -----
    const ctx = document.getElementById('messagesChart');
    if (!ctx) return;

    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.04)' : 'rgba(15,23,42,0.05)';
    const tickColor = isDark ? '#64748B' : '#94A3B8';

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartData['labels']) ?>,
            datasets: [
                {
                    label: 'Recibidos',
                    data: <?= json_encode($chartData['inbound']) ?>,
                    borderColor: '#7C3AED',
                    backgroundColor: ctx => {
                        const grad = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                        grad.addColorStop(0, 'rgba(124,58,237,0.35)');
                        grad.addColorStop(1, 'rgba(124,58,237,0)');
                        return grad;
                    },
                    fill: true,
                    tension: 0.42,
                    pointBackgroundColor: '#7C3AED',
                    pointBorderColor: isDark ? '#0F1530' : '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    borderWidth: 2.5,
                },
                {
                    label: 'Enviados',
                    data: <?= json_encode($chartData['outbound']) ?>,
                    borderColor: '#06B6D4',
                    backgroundColor: ctx => {
                        const grad = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                        grad.addColorStop(0, 'rgba(6,182,212,0.35)');
                        grad.addColorStop(1, 'rgba(6,182,212,0)');
                        return grad;
                    },
                    fill: true,
                    tension: 0.42,
                    pointBackgroundColor: '#06B6D4',
                    pointBorderColor: isDark ? '#0F1530' : '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    borderWidth: 2.5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            animations: {
                tension: { duration: 1500, easing: 'easeInOutCubic', from: 0.6, to: 0.42 }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#0F1530' : '#fff',
                    titleColor: isDark ? '#F8FAFC' : '#0F172A',
                    bodyColor: isDark ? '#CBD5E1' : '#475569',
                    borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6,
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    usePointStyle: true,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: tickColor, font: { size: 11 }, maxRotation: 0 },
                    border: { display: false }
                },
                y: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { color: tickColor, font: { size: 11 }, padding: 10 },
                    beginAtZero: true,
                    border: { display: false }
                }
            }
        }
    });
});
</script>

<?php \App\Core\View::stop(); ?>
