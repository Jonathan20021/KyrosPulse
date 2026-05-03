<?php
/**
 * @var array $tenant
 * @var array $categories
 * @var array $items
 * @var array $grouped
 * @var array $zones
 * @var string $waPhone
 * @var string $currency
 * @var string $menuUrl
 */
$brand   = (string) ($tenant['name'] ?? 'Restaurante');
$logoUrl = (string) ($tenant['logo_url'] ?? '');
$itemsForJs = [];
foreach ($items as $i) {
    $itemsForJs[(int) $i['id']] = [
        'id'       => (int) $i['id'],
        'name'     => (string) $i['name'],
        'price'    => (float) $i['price'],
        'currency' => (string) ($i['currency'] ?? $currency),
        'photo'    => (string) ($i['photo'] ?? ''),
        'category' => (int) ($i['category_id'] ?? 0),
    ];
}
?>
<!DOCTYPE html>
<html lang="es" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($brand) ?> — Menu</title>
    <meta name="description" content="Menu de <?= e($brand) ?>. Arma tu pedido y confirmalo por WhatsApp.">
    <meta name="theme-color" content="#0A0F25">
    <meta name="robots" content="noindex">
    <meta property="og:title" content="<?= e($brand) ?> — Menu online">
    <meta property="og:description" content="Pide directo. Confirmas por WhatsApp.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { color-scheme: dark; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .glass { background: rgba(255,255,255,.05); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,.08); }
        .card-img { aspect-ratio: 4/3; object-fit: cover; width: 100%; }
        .scroll-snap { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
        .scroll-snap > * { scroll-snap-align: start; }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: .6; } }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        .qty-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9999px; font-weight: 700; }
        .checkout-bar { box-shadow: 0 -10px 40px rgba(124,58,237,.35); }
        @media (min-width: 1024px) {
            .checkout-bar { display: none; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-[#050817] via-[#0A0F25] to-[#0d1233] text-slate-200 antialiased min-h-screen pb-32 lg:pb-8" x-data="menuApp()" x-init="init()">

<!-- Header -->
<header class="sticky top-0 z-30 backdrop-blur-md bg-[#050817]/85 border-b border-white/5">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($brand) ?>" class="w-10 h-10 rounded-xl object-cover">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl font-black" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">
                    <?= e(mb_strtoupper(mb_substr($brand, 0, 1))) ?>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="font-bold text-base sm:text-lg truncate"><?= e($brand) ?></h1>
                <p class="text-xs text-emerald-400 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                    Recibiendo pedidos
                </p>
            </div>
        </div>
        <button @click="cartOpen = true" class="relative px-3 py-2 rounded-xl flex items-center gap-2 text-sm font-semibold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">
            <span>🛒</span>
            <span class="hidden sm:inline">Mi orden</span>
            <span class="bg-white/20 rounded-full px-2 text-xs font-black" x-text="totalQty()" x-show="totalQty() > 0"></span>
        </button>
    </div>

    <!-- Sticky tabs categorias -->
    <?php if (!empty($categories)): ?>
    <nav class="border-t border-white/5 overflow-x-auto scroll-snap">
        <div class="max-w-6xl mx-auto px-4 flex gap-2 py-2 whitespace-nowrap">
            <?php foreach ($categories as $cat): if (empty($grouped[(int) $cat['id']])) continue; ?>
                <a href="#cat-<?= (int) $cat['id'] ?>" class="px-3 py-1.5 rounded-full text-xs font-medium glass hover:bg-white/10 transition-colors">
                    <?= e((string) ($cat['icon'] ?? '🍽')) ?> <?= e($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>
</header>

<main class="max-w-6xl mx-auto px-4 py-6">

<?php if (empty($items)): ?>
    <div class="glass rounded-2xl p-10 text-center">
        <div class="text-6xl mb-3">🍽</div>
        <h2 class="text-xl font-bold mb-1">Aun no hay platos</h2>
        <p class="text-sm text-slate-400">El restaurante esta cargando su menu. Vuelve pronto.</p>
    </div>
<?php else: ?>

<?php foreach ($categories as $cat):
    $catItems = $grouped[(int) $cat['id']] ?? [];
    if (empty($catItems)) continue;
?>
<section id="cat-<?= (int) $cat['id'] ?>" class="mb-8 scroll-mt-32">
    <div class="flex items-center gap-2 mb-3">
        <span class="text-2xl"><?= e((string) ($cat['icon'] ?? '🍽')) ?></span>
        <h2 class="text-xl sm:text-2xl font-black"><?= e($cat['name']) ?></h2>
    </div>
    <?php if (!empty($cat['description'])): ?>
        <p class="text-sm text-slate-400 mb-4"><?= e((string) $cat['description']) ?></p>
    <?php endif; ?>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($catItems as $i): ?>
            <?php
            $price = (float) $i['price'];
            $cur   = (string) ($i['currency'] ?? $currency);
            $hasPhoto = !empty($i['photo']);
            ?>
            <article class="glass rounded-2xl overflow-hidden flex flex-col">
                <?php if ($hasPhoto): ?>
                    <img src="<?= e((string) $i['photo']) ?>" alt="<?= e((string) $i['name']) ?>" class="card-img" loading="lazy">
                <?php else: ?>
                    <div class="card-img flex items-center justify-center text-5xl" style="background: linear-gradient(135deg,rgba(124,58,237,.18),rgba(6,182,212,.18))">🍽</div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-bold text-base"><?= e((string) $i['name']) ?></h3>
                        <?php if (!empty($i['is_featured'])): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 font-semibold uppercase tracking-wider">★ Top</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($i['description'])): ?>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-3"><?= e((string) $i['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto pt-3 flex items-center justify-between gap-2">
                        <span class="font-black text-lg"><?= e($cur) ?> <?= number_format($price, 2) ?></span>
                        <div x-data="{ qty: cart[<?= (int) $i['id'] ?>]?.qty || 0 }" x-init="$watch('cart', v => qty = v[<?= (int) $i['id'] ?>]?.qty || 0)" class="flex items-center gap-2">
                            <template x-if="qty === 0">
                                <button @click="add(<?= (int) $i['id'] ?>)" class="px-4 py-2 rounded-xl text-white text-sm font-bold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">+ Agregar</button>
                            </template>
                            <template x-if="qty > 0">
                                <div class="flex items-center gap-1 glass rounded-full">
                                    <button @click="decrement(<?= (int) $i['id'] ?>)" class="qty-btn hover:bg-white/10 text-rose-400">−</button>
                                    <span class="font-bold w-6 text-center text-sm" x-text="qty"></span>
                                    <button @click="add(<?= (int) $i['id'] ?>)" class="qty-btn hover:bg-white/10 text-emerald-400">+</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php $uncat = $grouped['_uncat'] ?? []; if (!empty($uncat)): ?>
<section class="mb-8">
    <h2 class="text-xl font-black mb-3">Otros</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($uncat as $i):
            $price = (float) $i['price'];
            $cur   = (string) ($i['currency'] ?? $currency);
        ?>
            <article class="glass rounded-2xl overflow-hidden flex flex-col">
                <?php if (!empty($i['photo'])): ?>
                    <img src="<?= e((string) $i['photo']) ?>" alt="<?= e((string) $i['name']) ?>" class="card-img" loading="lazy">
                <?php else: ?>
                    <div class="card-img flex items-center justify-center text-5xl" style="background: linear-gradient(135deg,rgba(124,58,237,.18),rgba(6,182,212,.18))">🍽</div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-bold"><?= e((string) $i['name']) ?></h3>
                    <?php if (!empty($i['description'])): ?>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-3"><?= e((string) $i['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto pt-3 flex items-center justify-between gap-2">
                        <span class="font-black text-lg"><?= e($cur) ?> <?= number_format($price, 2) ?></span>
                        <div x-data="{ qty: cart[<?= (int) $i['id'] ?>]?.qty || 0 }" x-init="$watch('cart', v => qty = v[<?= (int) $i['id'] ?>]?.qty || 0)" class="flex items-center gap-2">
                            <template x-if="qty === 0">
                                <button @click="add(<?= (int) $i['id'] ?>)" class="px-4 py-2 rounded-xl text-white text-sm font-bold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">+ Agregar</button>
                            </template>
                            <template x-if="qty > 0">
                                <div class="flex items-center gap-1 glass rounded-full">
                                    <button @click="decrement(<?= (int) $i['id'] ?>)" class="qty-btn hover:bg-white/10 text-rose-400">−</button>
                                    <span class="font-bold w-6 text-center text-sm" x-text="qty"></span>
                                    <button @click="add(<?= (int) $i['id'] ?>)" class="qty-btn hover:bg-white/10 text-emerald-400">+</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>

</main>

<!-- Mobile sticky checkout bar -->
<div x-show="totalQty() > 0" x-transition class="checkout-bar fixed bottom-0 inset-x-0 z-30 bg-[#0A0F25]/95 backdrop-blur-lg border-t border-white/5 px-4 py-3" style="display:none">
    <div class="max-w-6xl mx-auto flex items-center justify-between gap-3">
        <div>
            <p class="text-xs text-slate-400">Total estimado</p>
            <p class="font-black text-xl" x-text="formatCurrency(subtotal())"></p>
        </div>
        <button @click="cartOpen = true" class="px-6 py-3 rounded-xl text-white font-bold text-sm flex items-center gap-2" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">
            <span x-text="totalQty() + ' items'"></span>
            <span>→</span>
            <span>Continuar</span>
        </button>
    </div>
</div>

<!-- Cart sidebar drawer -->
<div x-show="cartOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm" @click="cartOpen = false" style="display:none"></div>
<aside x-show="cartOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
       class="fixed top-0 right-0 z-50 h-full w-full sm:w-[440px] bg-[#0A0F25] border-l border-white/10 flex flex-col" style="display:none">
    <header class="flex items-center justify-between px-5 py-4 border-b border-white/5">
        <h2 class="font-bold text-lg">🛒 Tu orden</h2>
        <button @click="cartOpen = false" class="text-2xl text-slate-400 hover:text-white">×</button>
    </header>

    <div class="flex-1 overflow-y-auto px-5 py-4">
        <template x-if="totalQty() === 0">
            <div class="text-center py-16">
                <div class="text-5xl mb-3 opacity-50">🛒</div>
                <p class="text-slate-400">Tu carrito esta vacio.</p>
                <button @click="cartOpen = false" class="mt-4 px-4 py-2 rounded-xl text-sm font-medium glass hover:bg-white/10">Seguir viendo</button>
            </div>
        </template>

        <template x-if="totalQty() > 0">
            <div>
                <ul class="space-y-3 mb-6">
                    <template x-for="line in lines()" :key="line.id">
                        <li class="glass rounded-xl p-3 flex items-center gap-3">
                            <template x-if="line.photo">
                                <img :src="line.photo" :alt="line.name" class="w-14 h-14 rounded-lg object-cover">
                            </template>
                            <template x-if="!line.photo">
                                <div class="w-14 h-14 rounded-lg flex items-center justify-center text-2xl" style="background: linear-gradient(135deg,rgba(124,58,237,.18),rgba(6,182,212,.18))">🍽</div>
                            </template>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm truncate" x-text="line.name"></p>
                                <p class="text-xs text-slate-400" x-text="line.currency + ' ' + line.price.toFixed(2)"></p>
                            </div>
                            <div class="flex items-center gap-1 glass rounded-full">
                                <button @click="decrement(line.id)" class="qty-btn hover:bg-white/10 text-rose-400">−</button>
                                <span class="font-bold w-6 text-center text-sm" x-text="line.qty"></span>
                                <button @click="add(line.id)" class="qty-btn hover:bg-white/10 text-emerald-400">+</button>
                            </div>
                        </li>
                    </template>
                </ul>

                <!-- Form datos cliente -->
                <div class="space-y-3 mb-4">
                    <h3 class="font-bold text-sm">Tus datos</h3>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-slate-400">Nombre *</label>
                        <input x-model="customer.name" type="text" required placeholder="Tu nombre"
                               class="w-full mt-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white">
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider text-slate-400">WhatsApp *</label>
                        <input x-model="customer.phone" type="tel" required placeholder="+58 412 ..." inputmode="tel"
                               class="w-full mt-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white">
                    </div>

                    <!-- Tipo de entrega -->
                    <div class="grid grid-cols-3 gap-2">
                        <button @click="customer.delivery_type='delivery'" :class="customer.delivery_type==='delivery' ? 'ring-2 ring-violet-500' : ''" class="glass rounded-xl py-2 px-2 text-xs font-semibold hover:bg-white/10">
                            🛵 Delivery
                        </button>
                        <button @click="customer.delivery_type='pickup'" :class="customer.delivery_type==='pickup' ? 'ring-2 ring-violet-500' : ''" class="glass rounded-xl py-2 px-2 text-xs font-semibold hover:bg-white/10">
                            🏃 Recoger
                        </button>
                        <button @click="customer.delivery_type='dine_in'" :class="customer.delivery_type==='dine_in' ? 'ring-2 ring-violet-500' : ''" class="glass rounded-xl py-2 px-2 text-xs font-semibold hover:bg-white/10">
                            🍽 Local
                        </button>
                    </div>

                    <template x-if="customer.delivery_type === 'delivery'">
                        <div class="space-y-3">
                            <?php if (!empty($zones)): ?>
                            <div>
                                <label class="text-xs uppercase tracking-wider text-slate-400">Zona</label>
                                <select x-model="customer.delivery_zone_id" class="w-full mt-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white">
                                    <option value="">Selecciona zona</option>
                                    <?php foreach ($zones as $z): ?>
                                        <option value="<?= (int) $z['id'] ?>" data-fee="<?= (float) $z['fee'] ?>">
                                            <?= e((string) $z['name']) ?>
                                            <?php if ((float) $z['fee'] > 0): ?>
                                                — <?= e($currency) ?> <?= number_format((float) $z['fee'], 2) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div>
                                <label class="text-xs uppercase tracking-wider text-slate-400">Direccion</label>
                                <input x-model="customer.address" type="text" placeholder="Calle, numero, apartamento, referencia"
                                       class="w-full mt-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white">
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="text-xs uppercase tracking-wider text-slate-400">Nota para la cocina (opcional)</label>
                        <textarea x-model="customer.note" rows="2" placeholder="Sin cebolla, termino medio, etc."
                                  class="w-full mt-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white"></textarea>
                    </div>
                </div>

                <!-- Totales -->
                <div class="glass rounded-xl p-4 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Subtotal</span>
                        <span x-text="formatCurrency(subtotal())"></span>
                    </div>
                    <template x-if="deliveryFee() > 0">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Delivery</span>
                            <span x-text="formatCurrency(deliveryFee())"></span>
                        </div>
                    </template>
                    <div class="flex justify-between font-black text-base pt-2 border-t border-white/10 mt-2">
                        <span>Total</span>
                        <span x-text="formatCurrency(grandTotal())"></span>
                    </div>
                </div>

                <p x-show="error" x-text="error" class="mt-3 text-sm text-rose-400"></p>
            </div>
        </template>
    </div>

    <footer class="px-5 py-4 border-t border-white/5 bg-[#0A0F25]" x-show="totalQty() > 0">
        <button @click="checkout()" :disabled="loading"
                class="w-full py-3.5 rounded-xl text-white font-bold flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                style="background: linear-gradient(135deg,#25D366,#128C7E)">
            <template x-if="!loading">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.371-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>Confirmar por WhatsApp</span>
                </span>
            </template>
            <template x-if="loading">
                <span>Procesando...</span>
            </template>
        </button>
        <p class="text-[10px] text-center text-slate-500 mt-2">Se abrira WhatsApp con tu orden lista para enviar.</p>
    </footer>
</aside>

<footer class="text-center text-xs text-slate-500 py-6 px-4">
    <p>Pedidos atendidos por <?= e($brand) ?>. Tu orden se confirma cuando envies el mensaje de WhatsApp.</p>
</footer>

<script>
window.MENU_ITEMS = <?= json_encode($itemsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.MENU_ZONES = <?= json_encode(array_map(fn($z) => ['id' => (int) $z['id'], 'fee' => (float) $z['fee']], $zones), JSON_UNESCAPED_UNICODE) ?>;
window.MENU_CHECKOUT_URL = <?= json_encode(url('/m/' . $tenant['uuid'] . '/checkout')) ?>;
window.MENU_CURRENCY = <?= json_encode($currency) ?>;
window.WA_PHONE = <?= json_encode($waPhone) ?>;

function menuApp() {
    return {
        cart: {},
        cartOpen: false,
        loading: false,
        error: '',
        customer: {
            name: '',
            phone: '',
            delivery_type: 'delivery',
            delivery_zone_id: '',
            address: '',
            note: '',
        },
        init() {
            try {
                const saved = localStorage.getItem('kp_cart_<?= e($tenant['uuid']) ?>');
                if (saved) this.cart = JSON.parse(saved);
            } catch (e) { this.cart = {}; }
            this.$watch('cart', v => {
                try { localStorage.setItem('kp_cart_<?= e($tenant['uuid']) ?>', JSON.stringify(v)); } catch (e) {}
            });
        },
        add(id) {
            const item = window.MENU_ITEMS[id];
            if (!item) return;
            const cur = this.cart[id]?.qty || 0;
            this.cart = { ...this.cart, [id]: { id, qty: cur + 1 } };
        },
        decrement(id) {
            const cur = this.cart[id]?.qty || 0;
            if (cur <= 1) {
                const next = { ...this.cart };
                delete next[id];
                this.cart = next;
            } else {
                this.cart = { ...this.cart, [id]: { id, qty: cur - 1 } };
            }
        },
        totalQty() {
            return Object.values(this.cart).reduce((a, b) => a + (b.qty || 0), 0);
        },
        lines() {
            return Object.values(this.cart).map(c => {
                const it = window.MENU_ITEMS[c.id];
                if (!it) return null;
                return { ...it, qty: c.qty };
            }).filter(Boolean);
        },
        subtotal() {
            return this.lines().reduce((acc, l) => acc + (l.price * l.qty), 0);
        },
        deliveryFee() {
            if (this.customer.delivery_type !== 'delivery') return 0;
            const z = window.MENU_ZONES.find(x => String(x.id) === String(this.customer.delivery_zone_id));
            return z ? z.fee : 0;
        },
        grandTotal() {
            return this.subtotal() + this.deliveryFee();
        },
        formatCurrency(n) {
            return window.MENU_CURRENCY + ' ' + Number(n).toFixed(2);
        },
        async checkout() {
            this.error = '';
            if (!this.customer.name.trim()) { this.error = 'Falta tu nombre.'; return; }
            if (!this.customer.phone.trim()) { this.error = 'Falta tu numero de WhatsApp.'; return; }
            if (this.customer.delivery_type === 'delivery' && !this.customer.address.trim()) {
                this.error = 'Falta la direccion de entrega.'; return;
            }
            this.loading = true;
            try {
                const r = await fetch(window.MENU_CHECKOUT_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        items: Object.values(this.cart),
                        name:  this.customer.name,
                        phone: this.customer.phone,
                        delivery_type: this.customer.delivery_type,
                        delivery_zone_id: this.customer.delivery_zone_id || null,
                        address: this.customer.address,
                        note: this.customer.note,
                    }),
                });
                const data = await r.json();
                if (!r.ok || !data.success) {
                    this.error = data.error || 'No se pudo procesar tu orden.';
                    this.loading = false;
                    return;
                }
                // Limpiar carrito local antes de redirigir
                localStorage.removeItem('kp_cart_<?= e($tenant['uuid']) ?>');
                this.cart = {};
                window.location.href = data.wa_url;
            } catch (e) {
                this.error = 'Error de red. Intenta de nuevo.';
                this.loading = false;
            }
        }
    };
}
</script>
</body>
</html>
