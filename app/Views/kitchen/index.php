<?php
/** @var array $statuses */
/** @var array $statusFlow */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<div class="kds-shell" x-data="kdsApp()" x-init="init()" x-cloak>
    <!-- Header sticky con KPIs por estado -->
    <div class="kds-header">
        <div class="kds-header-left">
            <h1 class="kds-title">
                <span class="kds-title-icon">🍳</span>
                Cocina en vivo
            </h1>
            <div class="kds-clock">
                <span class="kds-pulse"></span>
                <span x-text="clock"></span>
                <span class="kds-help" title="Actualiza cada 6 segundos">· auto</span>
            </div>
        </div>

        <div class="kds-kpis">
            <?php foreach (['new'=>'#06B6D4','confirmed'=>'#3B82F6','preparing'=>'#F59E0B','ready'=>'#A855F7','out_for_delivery'=>'#EC4899'] as $st => $col):
                [$lbl, $stCol, $emoji] = $statuses[$st];
            ?>
            <button type="button"
                    class="kds-kpi-pill"
                    :class="filter === '<?= $st ?>' ? 'is-active' : ''"
                    @click="filter = (filter === '<?= $st ?>' ? '' : '<?= $st ?>'); fetchFeed()"
                    style="--kpi-color: <?= e($col) ?>;">
                <span class="kds-kpi-dot"></span>
                <span class="kds-kpi-emoji"><?= $emoji ?></span>
                <span class="kds-kpi-label"><?= e($lbl) ?></span>
                <span class="kds-kpi-count" x-text="kpis['<?= $st ?>'] || 0"></span>
            </button>
            <?php endforeach; ?>

            <div class="kds-kpi-pill kds-kpi-delivered">
                <span class="kds-kpi-emoji">✅</span>
                <span class="kds-kpi-label">Hoy</span>
                <span class="kds-kpi-count" x-text="deliveredToday"></span>
            </div>
        </div>

        <div class="kds-toolbar">
            <button type="button"
                    class="kds-toggle"
                    :class="showAll ? 'is-on' : ''"
                    @click="showAll = !showAll; filter = ''; fetchFeed()">
                <span x-text="showAll ? '☑ Mostrando todo hoy' : '☐ Solo activas'"></span>
            </button>
            <button type="button"
                    class="kds-toggle"
                    :class="soundOn ? 'is-on' : ''"
                    @click="soundOn = !soundOn; localStorage.setItem('kds_sound', soundOn ? '1' : '0')">
                <span x-text="soundOn ? '🔔 Sonido' : '🔕 Mudo'"></span>
            </button>
            <button type="button" class="kds-toggle" @click="fetchFeed()" title="Refrescar ahora">
                ↻ Ahora
            </button>
        </div>
    </div>

    <!-- Empty state -->
    <template x-if="!loading && orders.length === 0">
        <div class="kds-empty">
            <div class="kds-empty-icon">🍽</div>
            <h2 class="kds-empty-title" x-text="filter ? 'Sin ordenes en este estado' : 'Sin ordenes activas'"></h2>
            <p class="kds-empty-desc">Las ordenes nuevas apareceran aqui automaticamente.</p>
        </div>
    </template>

    <!-- Order grid -->
    <div class="kds-grid">
        <template x-for="o in orders" :key="o.id">
            <div class="kds-card"
                 :class="[
                     'kds-status-' + o.status,
                     'kds-urg-' + o.urgency,
                     o.is_ai ? 'kds-from-ai' : ''
                 ]">

                <div class="kds-card-head">
                    <div class="kds-card-left">
                        <div class="kds-card-code" x-text="codeShort(o.code)"></div>
                        <div class="kds-card-meta">
                            <span class="kds-card-icon" :title="deliveryLabel(o.delivery_type)">
                                <span x-text="deliveryIcon(o.delivery_type)"></span>
                            </span>
                            <span class="kds-card-icon" :title="'Pago: ' + (o.payment_method || 'no especificado')">
                                <span x-text="paymentIcon(o.payment_method)"></span>
                            </span>
                            <template x-if="o.is_ai">
                                <span class="kds-card-icon kds-icon-ai" title="Creada por IA">🤖</span>
                            </template>
                        </div>
                    </div>
                    <div class="kds-card-right">
                        <div class="kds-card-status" x-text="statusLabel(o.status)"></div>
                        <div class="kds-card-elapsed" x-text="o.elapsed_label"></div>
                    </div>
                </div>

                <div class="kds-card-customer">
                    <div class="kds-customer-name" x-text="o.customer_name"></div>
                    <div class="kds-customer-meta">
                        <span class="kds-customer-phone" x-text="o.customer_phone || ''"></span>
                        <span class="kds-customer-sep" x-show="o.customer_phone">·</span>
                        <span x-text="formatTime(o.created_at)"></span>
                    </div>
                </div>

                <ul class="kds-items">
                    <template x-for="it in o.items" :key="it.id">
                        <li class="kds-item">
                            <span class="kds-item-qty" x-text="it.qty + 'x'"></span>
                            <span class="kds-item-body">
                                <span class="kds-item-name" x-text="it.name"></span>
                                <template x-if="it.modifiers && it.modifiers.length">
                                    <span class="kds-item-mods" x-text="'*' + it.modifiers.map(m => typeof m === 'string' ? m : (m.name || m.label || '')).filter(Boolean).join(', ')"></span>
                                </template>
                                <template x-if="it.notes">
                                    <span class="kds-item-note" x-text="it.notes"></span>
                                </template>
                            </span>
                        </li>
                    </template>
                    <template x-if="!o.items || o.items.length === 0">
                        <li class="kds-item kds-item-empty">Sin items registrados</li>
                    </template>
                </ul>

                <template x-if="o.kitchen_notes">
                    <div class="kds-notice kds-notice-amber">
                        <span>⚠</span>
                        <span x-text="o.kitchen_notes"></span>
                    </div>
                </template>
                <template x-if="o.delivery_notes && o.delivery_type === 'delivery'">
                    <div class="kds-notice kds-notice-cyan">
                        <span>📍</span>
                        <span x-text="(o.delivery_address ? o.delivery_address + ' · ' : '') + o.delivery_notes"></span>
                    </div>
                </template>
                <template x-if="!o.delivery_notes && o.delivery_address && o.delivery_type === 'delivery'">
                    <div class="kds-notice kds-notice-cyan">
                        <span>📍</span>
                        <span x-text="o.delivery_address"></span>
                    </div>
                </template>

                <div class="kds-card-foot">
                    <div class="kds-card-total">
                        <span class="kds-total-currency" x-text="o.currency"></span>
                        <span class="kds-total-value" x-text="formatMoney(o.total)"></span>
                    </div>
                    <div class="kds-card-actions">
                        <template x-for="next in o.next_states" :key="next">
                            <button type="button"
                                    class="kds-btn"
                                    :class="next === 'cancelled' ? 'kds-btn-danger' : 'kds-btn-primary'"
                                    @click="transition(o.id, next)"
                                    :title="'Marcar como ' + statusLabel(next)">
                                <span x-text="statusEmoji(next)"></span>
                                <span x-text="statusLabel(next)"></span>
                            </button>
                        </template>
                        <a class="kds-btn kds-btn-ghost" :href="'<?= url('/orders/') ?>' + o.id" title="Ver detalle">↗</a>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <audio id="kdsDing" preload="auto" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA="></audio>
</div>

<script>
const KDS_STATUS_LABELS = <?= json_encode(array_map(fn($s) => $s[0], $statuses)) ?>;
const KDS_STATUS_EMOJI  = <?= json_encode(array_map(fn($s) => $s[2], $statuses)) ?>;
const KDS_CSRF = '<?= csrf_token() ?>';

function kdsApp() {
    return {
        orders: [],
        kpis: { new: 0, confirmed: 0, preparing: 0, ready: 0, out_for_delivery: 0 },
        deliveredToday: 0,
        loading: true,
        filter: '',
        showAll: false,
        soundOn: localStorage.getItem('kds_sound') !== '0',
        clock: '',
        knownIds: new Set(),
        timer: null,
        clockTimer: null,

        init() {
            this.tickClock();
            this.fetchFeed(true);
            this.timer = setInterval(() => this.fetchFeed(), 6000);
            this.clockTimer = setInterval(() => this.tickClock(), 1000);
            window.addEventListener('beforeunload', () => {
                clearInterval(this.timer);
                clearInterval(this.clockTimer);
            });
        },

        tickClock() {
            const d = new Date();
            this.clock = d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        async fetchFeed(isFirst) {
            try {
                const url = new URL('<?= url('/kitchen/feed') ?>', window.location.origin);
                if (this.showAll) url.searchParams.set('show', 'all');
                if (this.filter)  url.searchParams.set('status', this.filter);
                const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) return;

                // Detectar ordenes nuevas para alerta sonora
                const newOnes = (data.orders || []).filter(o => o.status === 'new' && !this.knownIds.has(o.id));
                if (!isFirst && this.soundOn && newOnes.length > 0) {
                    this.playDing();
                }

                this.orders = data.orders || [];
                this.kpis   = data.kpis || this.kpis;
                this.deliveredToday = data.delivered_today || 0;
                this.loading = false;
                this.knownIds = new Set(this.orders.map(o => o.id));
            } catch (e) {
                console.warn('kds feed error', e);
            }
        },

        async transition(orderId, newStatus) {
            const fd = new FormData();
            fd.append('status', newStatus);
            fd.append('_token', KDS_CSRF);
            try {
                const res = await fetch('<?= url('/kitchen/') ?>' + orderId + '/status', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': KDS_CSRF, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!data.ok) {
                    alert('No se pudo actualizar la orden.');
                    return;
                }
                // Refresh inmediato sin esperar el tick
                this.fetchFeed();
            } catch (e) {
                console.warn(e);
                alert('Error de red.');
            }
        },

        statusLabel(s) { return KDS_STATUS_LABELS[s] || s; },
        statusEmoji(s) { return KDS_STATUS_EMOJI[s] || '•'; },
        codeShort(code) {
            const m = String(code || '').match(/(\d+)$/);
            return m ? parseInt(m[1], 10) : (code || '');
        },
        deliveryIcon(t) {
            return { 'delivery':'🛵', 'pickup':'🛍', 'dine_in':'🍽' }[t] || '🛍';
        },
        deliveryLabel(t) {
            return { 'delivery':'Delivery', 'pickup':'Pickup', 'dine_in':'En local' }[t] || t;
        },
        paymentIcon(p) {
            const k = String(p || '').toLowerCase();
            if (k.includes('cash') || k.includes('efectivo')) return '💵';
            if (k.includes('card') || k.includes('tarjeta')) return '💳';
            if (k.includes('transfer'))                       return '🏦';
            if (k.includes('online') || k.includes('stripe')) return '🔗';
            return '💰';
        },
        formatTime(iso) {
            try {
                const d = new Date(iso.replace(' ', 'T'));
                return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            } catch (e) { return ''; }
        },
        formatMoney(n) {
            return new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);
        },
        playDing() {
            try {
                // Beep sintetizado (no requiere asset externo)
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine'; o.frequency.value = 880;
                g.gain.setValueAtTime(0.15, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
                o.start();
                o.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        },
    };
}
</script>

<?php \App\Core\View::stop(); ?>
