<?php
/**
 * @var array $tenant
 * @var array $settings
 * @var array $categories
 * @var array $items
 * @var array $grouped
 * @var array $featured
 * @var array $zones
 * @var string $waPhone
 * @var string $currency
 * @var string $menuUrl
 */
$brand     = (string) ($tenant['name'] ?? 'Restaurante');
$logoUrl   = (string) ($tenant['logo_url'] ?? '');
$cuisine   = (string) ($settings['cuisine_type'] ?? '');
$address   = (string) ($settings['address'] ?? '');
$prepMin   = (int) ($settings['order_prep_min'] ?? 25);
$minOrder  = (float) ($settings['min_order'] ?? 0);
$payments  = is_array($settings['payment_methods'] ?? null) ? $settings['payment_methods'] : ['cash','card'];
$initial   = mb_strtoupper(mb_substr($brand, 0, 1));

// Buscar foto destacada para el hero
$heroPhoto = '';
foreach ($featured as $f) {
    if (!empty($f['photo'])) { $heroPhoto = (string) $f['photo']; break; }
}
if ($heroPhoto === '') {
    foreach ($items as $i) {
        if (!empty($i['photo'])) { $heroPhoto = (string) $i['photo']; break; }
    }
}

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
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($brand) ?> · Menu</title>
    <meta name="description" content="Menu de <?= e($brand) ?>. Arma tu pedido y confirmalo por WhatsApp en segundos.">
    <meta name="theme-color" content="#13100d">
    <meta name="robots" content="noindex">
    <meta property="og:title" content="<?= e($brand) ?> · Menu online">
    <meta property="og:description" content="Pide directo. Confirmas por WhatsApp.">
    <?php if ($heroPhoto): ?><meta property="og:image" content="<?= e($heroPhoto) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                screens: {
                    'xs': '400px',
                    'sm': '640px',
                    'md': '768px',
                    'lg': '1024px',
                    'xl': '1280px',
                },
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Fraunces', 'Georgia', 'serif'],
                    },
                    colors: {
                        bg:      '#13100d',
                        bg2:     '#1c1813',
                        ink:     '#f8f3e8',
                        muted:   '#a8a193',
                        line:    'rgba(212,165,116,.14)',
                        gold:    '#d4a574',
                        amber:   '#f5a524',
                        ember:   '#ff6b35',
                        leaf:    '#7fb069',
                    },
                }
            }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            color-scheme: dark;
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-left: env(safe-area-inset-left, 0px);
            --safe-right: env(safe-area-inset-right, 0px);
            --header-h: 68px;
        }
        @media (min-width: 640px) { :root { --header-h: 76px; } }
        * { -webkit-tap-highlight-color: transparent; }
        html, body { font-family: 'Inter', system-ui, sans-serif; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(212,165,116,.10), transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 100%, rgba(255,107,53,.06), transparent 60%),
                #13100d;
            color: #f8f3e8;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
            min-height: 100vh;
            min-height: 100dvh;
            padding-left: var(--safe-left);
            padding-right: var(--safe-right);
        }
        img, video { max-width: 100%; height: auto; }
        .font-display { font-family: 'Fraunces', Georgia, serif; font-feature-settings: 'ss01' 1, 'ss02' 1; letter-spacing: -.02em; }
        .surface {
            background: linear-gradient(180deg, rgba(248,243,232,.04), rgba(248,243,232,.02));
            border: 1px solid rgba(212,165,116,.12);
            box-shadow: 0 1px 0 rgba(255,255,255,.03) inset;
        }
        .surface-strong {
            background: linear-gradient(180deg, rgba(248,243,232,.06), rgba(248,243,232,.03));
            border: 1px solid rgba(212,165,116,.20);
        }
        .ink-divider { background: linear-gradient(90deg, transparent, rgba(212,165,116,.35), transparent); height: 1px; }
        .pill {
            background: rgba(248,243,232,.04);
            border: 1px solid rgba(212,165,116,.18);
            color: #f8f3e8;
            transition: all .25s cubic-bezier(.2,.7,.3,1);
        }
        .pill:hover { background: rgba(212,165,116,.12); transform: translateY(-1px); }
        .pill.active {
            background: linear-gradient(135deg, #d4a574, #f5a524);
            color: #13100d;
            border-color: transparent;
            box-shadow: 0 4px 14px rgba(245,165,36,.35);
            font-weight: 700;
        }
        .card-photo { aspect-ratio: 4/3; object-fit: cover; width: 100%; transition: transform .65s cubic-bezier(.2,.7,.3,1); }
        .item-card:hover .card-photo { transform: scale(1.04); }
        .item-card { transition: transform .35s cubic-bezier(.2,.7,.3,1), border-color .35s; }
        .item-card:hover { transform: translateY(-4px); border-color: rgba(212,165,116,.32); }
        .placeholder-art {
            aspect-ratio: 4/3;
            background:
                radial-gradient(circle at 30% 30%, rgba(245,165,36,.18), transparent 55%),
                radial-gradient(circle at 70% 70%, rgba(255,107,53,.12), transparent 55%),
                linear-gradient(135deg, rgba(212,165,116,.10), rgba(248,243,232,.04));
        }
        .ribbon {
            background: linear-gradient(135deg, #f5a524, #ff6b35);
            color: #13100d;
            font-weight: 800;
            letter-spacing: .04em;
        }
        .scroll-snap-x { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
        .scroll-snap-x > * { scroll-snap-align: start; }
        .qty-btn {
            width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 9999px; font-weight: 700; font-size: 16px;
            transition: background .2s, transform .15s;
            touch-action: manipulation;
        }
        .qty-btn:hover { background: rgba(212,165,116,.18); }
        .qty-btn:active { transform: scale(.92); }
        .add-btn {
            background: linear-gradient(135deg, #f5a524, #ff6b35);
            color: #13100d;
            font-weight: 800;
            transition: transform .2s, box-shadow .2s, filter .2s;
            box-shadow: 0 6px 20px rgba(245,165,36,.25);
            min-height: 40px;
            touch-action: manipulation;
        }
        .add-btn:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 10px 28px rgba(245,165,36,.4); }
        .add-btn:active { transform: scale(.96); }
        .wa-btn {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
            box-shadow: 0 14px 30px rgba(37,211,102,.35);
            transition: transform .2s, box-shadow .2s, filter .2s;
        }
        .wa-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .wa-btn:active { transform: scale(.97); }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        .pulse-dot { animation: pulse 2.4s infinite; }
        @keyframes pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: .55; } }
        .float-up { animation: floatUp .55s cubic-bezier(.2,.7,.3,1) both; }
        @keyframes floatUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        .bounce { animation: bounce .45s cubic-bezier(.25,.9,.4,1.4); }
        @keyframes bounce { 0% { transform: scale(1); } 30% { transform: scale(1.18); } 100% { transform: scale(1); } }
        .input-field {
            width: 100%;
            padding: 12px 14px;
            background: rgba(248,243,232,.03);
            border: 1px solid rgba(212,165,116,.20);
            border-radius: 12px;
            color: #f8f3e8;
            transition: border-color .2s, background .2s;
            font-size: 15px;
        }
        .input-field::placeholder { color: rgba(168,161,147,.6); }
        .input-field:focus { outline: none; border-color: #d4a574; background: rgba(248,243,232,.05); box-shadow: 0 0 0 3px rgba(212,165,116,.15); }
        .label-mini { font-size: 11px; text-transform: uppercase; letter-spacing: .12em; color: #a8a193; font-weight: 600; }
        .price-tag { font-family: 'Fraunces', Georgia, serif; font-feature-settings: 'tnum' 1; font-weight: 700; }
        .delivery-tab {
            border: 1px solid rgba(212,165,116,.18);
            background: rgba(248,243,232,.03);
            transition: all .2s;
            font-weight: 600;
        }
        .delivery-tab:hover { background: rgba(212,165,116,.08); }
        .delivery-tab.active {
            background: linear-gradient(135deg, rgba(212,165,116,.18), rgba(245,165,36,.12));
            border-color: #d4a574;
            color: #f8f3e8;
        }
        .checkout-bar {
            box-shadow: 0 -16px 40px rgba(245,165,36,.25);
            padding-bottom: calc(16px + var(--safe-bottom));
        }
        @media (min-width: 1024px) { .checkout-bar { display: none !important; } }
        .drawer-footer { padding-bottom: calc(20px + var(--safe-bottom)); }
        .drawer-pad-top { padding-top: calc(20px + var(--safe-top)); }
        /* Sticky offsets que respetan altura real del header */
        .nav-sticky { top: var(--header-h); }
        .scroll-mt-cat { scroll-margin-top: calc(var(--header-h) + 64px); }
        .empty-cart-art {
            background:
                radial-gradient(circle at 50% 50%, rgba(245,165,36,.12), transparent 60%);
        }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .shimmer-text {
            background: linear-gradient(90deg, #d4a574 25%, #f8f3e8 50%, #d4a574 75%);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shimmer 6s linear infinite;
        }
        ::selection { background: rgba(245,165,36,.4); color: #13100d; }

        /* iOS evita zoom al focus si font-size >= 16px */
        @media (max-width: 640px) {
            .input-field { font-size: 16px; }
        }
        /* Pantallas muy estrechas (Galaxy Fold cerrado, etc.) */
        @media (max-width: 360px) {
            .hero-title { font-size: 2rem !important; line-height: 1; }
            .hero-stats { font-size: 11px; }
            .item-card .price-tag { font-size: 1.05rem; }
            .add-btn { padding: .5rem .85rem; font-size: .8rem; }
            .qty-btn { width: 32px; height: 32px; font-size: 14px; }
        }
        /* Landscape de telefono: drawer sigue cabiendo, header mas compacto */
        @media (max-height: 480px) and (orientation: landscape) {
            .hero-pad { padding-top: 1.5rem; padding-bottom: 1.5rem; }
            .hero-title { font-size: 2.25rem !important; }
        }
        /* Reducir motion para usuarios con preferencia */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body class="min-h-screen" style="padding-bottom: calc(7rem + var(--safe-bottom));" x-data="menuApp()" x-init="init()"
      :class="cartOpen && 'overflow-hidden'"
      x-effect="document.documentElement.style.overflow = cartOpen ? 'hidden' : ''">

<!-- Sticky header -->
<header class="sticky top-0 z-30 backdrop-blur-xl" style="background: rgba(19,16,13,.78); border-bottom: 1px solid rgba(212,165,116,.12);">
    <div class="max-w-6xl mx-auto px-3 sm:px-6 py-2.5 sm:py-3 flex items-center justify-between gap-2 sm:gap-3 min-h-[68px] sm:min-h-[76px]">
        <a href="#top" class="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($brand) ?>" class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl object-cover ring-1 ring-gold/30 flex-shrink-0">
            <?php else: ?>
                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center text-lg sm:text-xl font-display font-black text-bg flex-shrink-0" style="background: linear-gradient(135deg,#d4a574,#f5a524);">
                    <?= e($initial) ?>
                </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1">
                <h1 class="font-display font-bold text-base sm:text-lg leading-tight truncate"><?= e($brand) ?></h1>
                <p class="text-[10px] sm:text-[11px] text-leaf flex items-center gap-1.5 mt-0.5 truncate">
                    <span class="w-1.5 h-1.5 rounded-full bg-leaf pulse-dot flex-shrink-0"></span>
                    <span class="truncate">Abierto · ~<?= $prepMin ?> min</span>
                </p>
            </div>
        </a>
        <button @click="cartOpen = true" class="relative px-3 sm:px-4 py-2.5 rounded-full flex items-center gap-1.5 sm:gap-2 text-sm font-bold add-btn flex-shrink-0">
            <svg class="w-4 h-4 sm:w-[18px] sm:h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="hidden sm:inline">Mi orden</span>
            <span x-show="totalQty() > 0" class="bg-bg/30 rounded-full px-2 py-0.5 text-xs font-black min-w-[22px] text-center" :class="bumpCart && 'bounce'" x-text="totalQty()"></span>
        </button>
    </div>
</header>

<!-- Hero / Cover -->
<section id="top" class="relative overflow-hidden">
    <?php if ($heroPhoto): ?>
        <div class="absolute inset-0">
            <img src="<?= e($heroPhoto) ?>" alt="" class="w-full h-full object-cover opacity-30 scale-105 blur-sm">
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(19,16,13,.5) 0%, rgba(19,16,13,.85) 60%, #13100d 100%);"></div>
        </div>
    <?php endif; ?>
    <div class="hero-pad relative max-w-6xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12 pb-10 sm:pb-16 text-center float-up">
        <?php if ($cuisine): ?>
            <p class="label-mini mb-2 sm:mb-3 text-gold"><?= e($cuisine) ?></p>
        <?php endif; ?>
        <h2 class="hero-title font-display font-black text-[2.25rem] xs:text-4xl sm:text-5xl md:text-6xl leading-[.95] mb-3 break-words">
            <span class="shimmer-text"><?= e($brand) ?></span>
        </h2>
        <p class="text-muted max-w-xl mx-auto text-sm sm:text-base md:text-lg px-2">Arma tu pedido en segundos. Confirmamos por WhatsApp.</p>

        <div class="ink-divider mx-auto my-5 sm:my-7 max-w-[180px] sm:max-w-xs"></div>

        <!-- Mini stats fila: en mobile cada chip en card propia, desktop en linea -->
        <div class="hero-stats grid grid-cols-2 sm:flex sm:flex-wrap sm:items-center sm:justify-center gap-2 sm:gap-x-6 sm:gap-y-3 text-xs sm:text-sm">
            <div class="surface sm:!bg-transparent sm:!border-0 rounded-xl sm:rounded-none px-3 py-2 sm:p-0 flex items-center gap-2 text-muted justify-center">
                <span class="text-gold text-base flex-shrink-0">⏱</span>
                <span class="truncate"><?= $prepMin ?> min</span>
            </div>
            <span class="text-line hidden sm:inline">·</span>
            <div class="surface sm:!bg-transparent sm:!border-0 rounded-xl sm:rounded-none px-3 py-2 sm:p-0 flex items-center gap-2 text-muted justify-center">
                <span class="text-gold text-base flex-shrink-0">🛵</span>
                <span class="truncate"><?= !empty($zones) ? count($zones) . ' zonas' : 'Delivery' ?></span>
            </div>
            <span class="text-line hidden sm:inline">·</span>
            <div class="surface sm:!bg-transparent sm:!border-0 rounded-xl sm:rounded-none px-3 py-2 sm:p-0 flex items-center gap-2 text-muted justify-center col-span-2 sm:col-span-1">
                <span class="text-gold text-base flex-shrink-0">💳</span>
                <span class="truncate"><?= e(implode(' · ', array_slice($payments, 0, 3))) ?></span>
            </div>
            <?php if ($address): ?>
            <span class="text-line hidden sm:inline">·</span>
            <div class="surface sm:!bg-transparent sm:!border-0 rounded-xl sm:rounded-none px-3 py-2 sm:p-0 flex items-center gap-2 text-muted justify-center col-span-2 sm:col-span-1">
                <span class="text-gold text-base flex-shrink-0">📍</span>
                <span class="truncate max-w-full sm:max-w-[200px]"><?= e($address) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (empty($items)): ?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 mb-12">
    <div class="surface rounded-3xl p-12 text-center">
        <div class="text-7xl mb-4 opacity-60">🍽</div>
        <h3 class="font-display font-bold text-2xl mb-2">Aun no hay platos</h3>
        <p class="text-muted">El restaurante esta cargando su menu. Vuelve pronto.</p>
    </div>
</section>
<?php else: ?>

<!-- Featured carousel (if any) -->
<?php if (!empty($featured) && count($featured) >= 2): ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mb-8 sm:mb-10">
    <div class="flex items-end justify-between mb-3 sm:mb-4">
        <div class="min-w-0">
            <p class="label-mini text-gold mb-1">Recomendados de la casa</p>
            <h3 class="font-display font-bold text-xl sm:text-2xl md:text-3xl">Lo mas pedido</h3>
        </div>
    </div>
    <div class="flex gap-3 sm:gap-4 overflow-x-auto scroll-snap-x pb-2 -mx-4 sm:-mx-6 px-4 sm:px-6" style="scrollbar-width: thin;">
        <?php foreach ($featured as $f):
            $price = (float) $f['price'];
            $cur   = (string) ($f['currency'] ?? $currency);
        ?>
        <article class="surface item-card rounded-2xl overflow-hidden flex-shrink-0 w-[230px] xs:w-[250px] sm:w-[280px] md:w-[300px]">
            <div class="relative">
                <?php if (!empty($f['photo'])): ?>
                    <img src="<?= e((string) $f['photo']) ?>" alt="<?= e((string) $f['name']) ?>" class="card-photo" loading="lazy">
                <?php else: ?>
                    <div class="placeholder-art flex items-center justify-center text-5xl">🍽</div>
                <?php endif; ?>
                <span class="absolute top-3 left-3 ribbon text-[10px] px-2.5 py-1 rounded-full">★ TOP</span>
            </div>
            <div class="p-4">
                <h4 class="font-display font-bold text-lg leading-tight mb-1 line-clamp-1"><?= e((string) $f['name']) ?></h4>
                <p class="text-xs text-muted line-clamp-2 mb-3 min-h-[2.5em]"><?= e((string) ($f['description'] ?? '')) ?></p>
                <div class="flex items-center justify-between gap-2">
                    <span class="price-tag text-lg text-gold"><?= e($cur) ?> <?= number_format($price, 2) ?></span>
                    <div x-data="{ qty: cart[<?= (int) $f['id'] ?>]?.qty || 0 }" x-init="$watch('cart', v => qty = v[<?= (int) $f['id'] ?>]?.qty || 0)">
                        <template x-if="qty === 0">
                            <button @click="add(<?= (int) $f['id'] ?>)" class="add-btn px-3.5 py-2 rounded-full text-xs whitespace-nowrap">+ Agregar</button>
                        </template>
                        <template x-if="qty > 0">
                            <div class="flex items-center gap-1 surface rounded-full px-1">
                                <button @click="decrement(<?= (int) $f['id'] ?>)" class="qty-btn text-ember" aria-label="Quitar uno">−</button>
                                <span class="font-bold text-sm w-5 text-center" x-text="qty"></span>
                                <button @click="add(<?= (int) $f['id'] ?>)" class="qty-btn text-leaf" aria-label="Agregar uno">+</button>
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

<!-- Sticky category nav (scroll-spy) -->
<?php if (!empty($categories)): ?>
<nav class="nav-sticky sticky z-20" style="background: rgba(19,16,13,.85); backdrop-filter: blur(14px); border-bottom: 1px solid rgba(212,165,116,.10);">
    <div class="max-w-6xl mx-auto overflow-x-auto" style="scrollbar-width: none; -ms-overflow-style: none;">
        <div class="flex gap-1.5 sm:gap-2 px-3 sm:px-6 py-2.5 sm:py-3 whitespace-nowrap">
            <?php foreach ($categories as $cat): if (empty($grouped[(int) $cat['id']])) continue; ?>
                <a href="#cat-<?= (int) $cat['id'] ?>"
                   :class="activeCategory === <?= (int) $cat['id'] ?> ? 'pill active' : 'pill'"
                   class="pill px-3 sm:px-4 py-1.5 rounded-full text-xs font-semibold inline-flex items-center gap-1.5 flex-shrink-0">
                    <span><?= e((string) ($cat['icon'] ?? '🍽')) ?></span>
                    <span><?= e($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 sm:pt-8 pb-12">

<?php foreach ($categories as $cat):
    $catItems = $grouped[(int) $cat['id']] ?? [];
    if (empty($catItems)) continue;
?>
<section id="cat-<?= (int) $cat['id'] ?>" class="mb-10 sm:mb-12 scroll-mt-cat" x-intersect="activeCategory = <?= (int) $cat['id'] ?>">
    <header class="flex items-end justify-between gap-3 mb-4 sm:mb-5">
        <div class="min-w-0">
            <p class="label-mini text-gold mb-1 sm:mb-1.5"><?= e((string) ($cat['icon'] ?? '')) ?> Categoria</p>
            <h3 class="font-display font-bold text-2xl sm:text-3xl md:text-4xl leading-tight"><?= e($cat['name']) ?></h3>
            <?php if (!empty($cat['description'])): ?>
                <p class="text-xs sm:text-sm text-muted mt-1.5 sm:mt-2 max-w-xl"><?= e((string) $cat['description']) ?></p>
            <?php endif; ?>
        </div>
        <span class="text-xs text-muted hidden sm:block flex-shrink-0"><?= count($catItems) ?> opciones</span>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        <?php foreach ($catItems as $i):
            $price = (float) $i['price'];
            $cur   = (string) ($i['currency'] ?? $currency);
            $hasPhoto = !empty($i['photo']);
        ?>
            <article class="item-card surface rounded-2xl overflow-hidden flex flex-col">
                <div class="relative overflow-hidden">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= e((string) $i['photo']) ?>" alt="<?= e((string) $i['name']) ?>" class="card-photo" loading="lazy">
                    <?php else: ?>
                        <div class="placeholder-art flex items-center justify-center text-5xl sm:text-6xl">🍽</div>
                    <?php endif; ?>
                    <?php if (!empty($i['is_featured'])): ?>
                        <span class="absolute top-2.5 left-2.5 sm:top-3 sm:left-3 ribbon text-[10px] px-2 sm:px-2.5 py-1 rounded-full">★ TOP</span>
                    <?php endif; ?>
                    <?php if (!empty($i['compare_price']) && (float) $i['compare_price'] > $price): ?>
                        <span class="absolute top-2.5 right-2.5 sm:top-3 sm:right-3 px-2 sm:px-2.5 py-1 rounded-full text-[10px] font-bold" style="background:#7fb069; color:#13100d;">OFERTA</span>
                    <?php endif; ?>
                </div>
                <div class="p-4 sm:p-5 flex-1 flex flex-col">
                    <h4 class="font-display font-bold text-base sm:text-lg leading-tight mb-1.5"><?= e((string) $i['name']) ?></h4>
                    <?php if (!empty($i['description'])): ?>
                        <p class="text-xs sm:text-sm text-muted line-clamp-2 mb-3 sm:mb-4"><?= e((string) $i['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto flex items-center justify-between gap-2 sm:gap-3 flex-wrap">
                        <div class="flex flex-col leading-tight">
                            <?php if (!empty($i['compare_price']) && (float) $i['compare_price'] > $price): ?>
                                <span class="text-[11px] sm:text-xs text-muted line-through"><?= e($cur) ?> <?= number_format((float) $i['compare_price'], 2) ?></span>
                            <?php endif; ?>
                            <span class="price-tag text-lg sm:text-xl text-ink"><?= e($cur) ?> <?= number_format($price, 2) ?></span>
                        </div>
                        <div x-data="{ qty: cart[<?= (int) $i['id'] ?>]?.qty || 0 }" x-init="$watch('cart', v => qty = v[<?= (int) $i['id'] ?>]?.qty || 0)" class="flex items-center gap-2">
                            <template x-if="qty === 0">
                                <button @click="add(<?= (int) $i['id'] ?>)" class="add-btn px-4 sm:px-5 py-2 rounded-full text-xs sm:text-sm">+ Agregar</button>
                            </template>
                            <template x-if="qty > 0">
                                <div class="flex items-center gap-1 surface rounded-full px-1.5">
                                    <button @click="decrement(<?= (int) $i['id'] ?>)" class="qty-btn text-ember" aria-label="Quitar uno">−</button>
                                    <span class="font-bold w-6 text-center text-sm" x-text="qty"></span>
                                    <button @click="add(<?= (int) $i['id'] ?>)" class="qty-btn text-leaf" aria-label="Agregar uno">+</button>
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
<section id="cat-others" class="mb-10 sm:mb-12 scroll-mt-cat" x-intersect="activeCategory = -1">
    <header class="mb-4 sm:mb-5">
        <p class="label-mini text-gold mb-1 sm:mb-1.5">Mas opciones</p>
        <h3 class="font-display font-bold text-2xl sm:text-3xl md:text-4xl">Otros</h3>
    </header>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        <?php foreach ($uncat as $i):
            $price = (float) $i['price'];
            $cur   = (string) ($i['currency'] ?? $currency);
        ?>
            <article class="item-card surface rounded-2xl overflow-hidden flex flex-col">
                <div class="relative overflow-hidden">
                    <?php if (!empty($i['photo'])): ?>
                        <img src="<?= e((string) $i['photo']) ?>" alt="<?= e((string) $i['name']) ?>" class="card-photo" loading="lazy">
                    <?php else: ?>
                        <div class="placeholder-art flex items-center justify-center text-5xl sm:text-6xl">🍽</div>
                    <?php endif; ?>
                </div>
                <div class="p-4 sm:p-5 flex-1 flex flex-col">
                    <h4 class="font-display font-bold text-base sm:text-lg leading-tight mb-1.5"><?= e((string) $i['name']) ?></h4>
                    <?php if (!empty($i['description'])): ?>
                        <p class="text-xs sm:text-sm text-muted line-clamp-2 mb-3 sm:mb-4"><?= e((string) $i['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto flex items-center justify-between gap-2 sm:gap-3 flex-wrap">
                        <span class="price-tag text-lg sm:text-xl text-ink"><?= e($cur) ?> <?= number_format($price, 2) ?></span>
                        <div x-data="{ qty: cart[<?= (int) $i['id'] ?>]?.qty || 0 }" x-init="$watch('cart', v => qty = v[<?= (int) $i['id'] ?>]?.qty || 0)" class="flex items-center gap-2">
                            <template x-if="qty === 0">
                                <button @click="add(<?= (int) $i['id'] ?>)" class="add-btn px-4 sm:px-5 py-2 rounded-full text-xs sm:text-sm">+ Agregar</button>
                            </template>
                            <template x-if="qty > 0">
                                <div class="flex items-center gap-1 surface rounded-full px-1.5">
                                    <button @click="decrement(<?= (int) $i['id'] ?>)" class="qty-btn text-ember" aria-label="Quitar uno">−</button>
                                    <span class="font-bold w-6 text-center text-sm" x-text="qty"></span>
                                    <button @click="add(<?= (int) $i['id'] ?>)" class="qty-btn text-leaf" aria-label="Agregar uno">+</button>
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
<div x-show="totalQty() > 0" x-transition.opacity.duration.300ms
     class="checkout-bar fixed bottom-0 inset-x-0 z-30 px-3 sm:px-4 pt-3"
     style="background: linear-gradient(180deg, transparent, rgba(19,16,13,.92) 25%); display:none">
    <div class="max-w-6xl mx-auto surface-strong rounded-2xl flex items-center justify-between gap-2 sm:gap-3 p-2.5 sm:p-3 backdrop-blur-xl">
        <div class="pl-1.5 sm:pl-2 min-w-0">
            <p class="text-[10px] text-muted uppercase tracking-wider">Total</p>
            <p class="price-tag text-lg sm:text-xl text-ink leading-none mt-0.5 truncate" x-text="formatCurrency(grandTotal())"></p>
        </div>
        <button @click="cartOpen = true" class="add-btn px-4 sm:px-5 py-2.5 sm:py-3 rounded-xl text-sm flex items-center gap-1.5 sm:gap-2 whitespace-nowrap flex-shrink-0">
            <span class="font-bold"><span x-text="totalQty()"></span> items</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
    </div>
</div>

<!-- Cart drawer -->
<div x-show="cartOpen" x-transition.opacity.duration.250ms class="fixed inset-0 z-40 bg-black/70 backdrop-blur-md" @click="cartOpen = false" style="display:none"></div>
<aside x-show="cartOpen"
       x-cloak
       x-transition:enter="transition ease-out duration-350"
       x-transition:enter-start="translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-220"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="translate-x-full"
       class="fixed top-0 right-0 z-50 w-full sm:w-[440px] md:w-[460px] flex flex-col"
       style="background: linear-gradient(180deg, #1c1813, #13100d); border-left: 1px solid rgba(212,165,116,.18); display:none; height: 100vh; height: 100dvh; max-height: 100dvh;">
    <header class="drawer-pad-top flex items-center justify-between px-5 sm:px-6 pb-4 sm:pb-5 flex-shrink-0" style="border-bottom: 1px solid rgba(212,165,116,.12);">
        <div class="min-w-0">
            <p class="label-mini text-gold">Tu pedido</p>
            <h3 class="font-display font-bold text-xl sm:text-2xl mt-0.5">
                <span x-show="totalQty() === 0">Carrito vacio</span>
                <span x-show="totalQty() > 0"><span x-text="totalQty()"></span> items</span>
            </h3>
        </div>
        <button @click="cartOpen = false" aria-label="Cerrar carrito" class="w-11 h-11 rounded-full flex items-center justify-center text-2xl text-muted hover:text-ink transition-colors hover:bg-white/5 flex-shrink-0">×</button>
    </header>

    <div class="flex-1 overflow-y-auto overscroll-contain px-5 sm:px-6 py-4 sm:py-5" style="-webkit-overflow-scrolling: touch;">
        <template x-if="totalQty() === 0">
            <div class="empty-cart-art rounded-3xl py-16 text-center">
                <div class="text-7xl mb-4 opacity-50">🛒</div>
                <h4 class="font-display font-bold text-xl mb-2">Tu carrito esta vacio</h4>
                <p class="text-sm text-muted mb-5">Explora el menu y arma tu pedido perfecto.</p>
                <button @click="cartOpen = false" class="surface px-5 py-2.5 rounded-full text-sm font-semibold hover:border-gold/40 transition-colors">Ver menu</button>
            </div>
        </template>

        <template x-if="totalQty() > 0">
            <div class="space-y-6">
                <!-- Items -->
                <ul class="space-y-2.5 sm:space-y-3">
                    <template x-for="line in lines()" :key="line.id">
                        <li class="surface rounded-2xl p-2.5 sm:p-3 flex items-center gap-2.5 sm:gap-3">
                            <template x-if="line.photo">
                                <img :src="line.photo" :alt="line.name" class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl object-cover flex-shrink-0">
                            </template>
                            <template x-if="!line.photo">
                                <div class="placeholder-art !aspect-square w-14 h-14 sm:w-16 sm:h-16 rounded-xl flex items-center justify-center text-xl sm:text-2xl flex-shrink-0">🍽</div>
                            </template>
                            <div class="flex-1 min-w-0">
                                <p class="font-display font-bold text-sm leading-tight" x-text="line.name" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"></p>
                                <p class="text-[11px] sm:text-xs text-muted mt-0.5 truncate">
                                    <span x-text="line.currency + ' ' + line.price.toFixed(2)"></span>
                                    <span class="text-gold"> · <span class="price-tag" x-text="line.currency + ' ' + (line.price * line.qty).toFixed(2)"></span></span>
                                </p>
                            </div>
                            <div class="flex items-center gap-0.5 surface rounded-full px-1 flex-shrink-0">
                                <button @click="decrement(line.id)" class="qty-btn text-ember" aria-label="Quitar uno">−</button>
                                <span class="font-bold w-5 sm:w-6 text-center text-sm" x-text="line.qty"></span>
                                <button @click="add(line.id)" class="qty-btn text-leaf" aria-label="Agregar uno">+</button>
                            </div>
                        </li>
                    </template>
                </ul>

                <div class="ink-divider"></div>

                <!-- Datos del cliente -->
                <div>
                    <p class="label-mini text-gold mb-3">Tus datos</p>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-muted block mb-1.5">Nombre completo *</label>
                            <input x-model="customer.name" type="text" required placeholder="Tu nombre" class="input-field">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted block mb-1.5">WhatsApp *</label>
                            <input x-model="customer.phone" type="tel" required placeholder="+58 412 ..." inputmode="tel" class="input-field">
                        </div>
                    </div>
                </div>

                <!-- Tipo de entrega -->
                <div>
                    <p class="label-mini text-gold mb-2.5 sm:mb-3">Como lo quieres</p>
                    <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                        <button @click="customer.delivery_type='delivery'" type="button"
                                :class="customer.delivery_type==='delivery' ? 'delivery-tab active' : 'delivery-tab'"
                                class="rounded-xl py-2.5 sm:py-3 px-1 sm:px-2 text-[11px] sm:text-xs flex flex-col items-center gap-1 min-h-[60px] justify-center">
                            <span class="text-lg sm:text-xl">🛵</span>
                            <span>Delivery</span>
                        </button>
                        <button @click="customer.delivery_type='pickup'" type="button"
                                :class="customer.delivery_type==='pickup' ? 'delivery-tab active' : 'delivery-tab'"
                                class="rounded-xl py-2.5 sm:py-3 px-1 sm:px-2 text-[11px] sm:text-xs flex flex-col items-center gap-1 min-h-[60px] justify-center">
                            <span class="text-lg sm:text-xl">🏃</span>
                            <span>Recoger</span>
                        </button>
                        <button @click="customer.delivery_type='dine_in'" type="button"
                                :class="customer.delivery_type==='dine_in' ? 'delivery-tab active' : 'delivery-tab'"
                                class="rounded-xl py-2.5 sm:py-3 px-1 sm:px-2 text-[11px] sm:text-xs flex flex-col items-center gap-1 min-h-[60px] justify-center">
                            <span class="text-lg sm:text-xl">🍽</span>
                            <span>En local</span>
                        </button>
                    </div>
                </div>

                <template x-if="customer.delivery_type === 'delivery'">
                    <div class="space-y-3">
                        <?php if (!empty($zones)): ?>
                        <div>
                            <label class="text-xs font-semibold text-muted block mb-1.5">Zona de entrega</label>
                            <select x-model="customer.delivery_zone_id" class="input-field">
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
                            <label class="text-xs font-semibold text-muted block mb-1.5">Direccion completa</label>
                            <input x-model="customer.address" type="text" placeholder="Calle, numero, apartamento, referencia" class="input-field">
                        </div>
                    </div>
                </template>

                <div>
                    <label class="text-xs font-semibold text-muted block mb-1.5">Nota para la cocina (opcional)</label>
                    <textarea x-model="customer.note" rows="2" placeholder="Sin cebolla, termino medio, alergias..." class="input-field resize-none"></textarea>
                </div>

                <div class="ink-divider"></div>

                <!-- Totales -->
                <div class="surface rounded-2xl p-4 space-y-2 text-sm">
                    <div class="flex justify-between text-muted">
                        <span>Subtotal</span>
                        <span class="text-ink price-tag" x-text="formatCurrency(subtotal())"></span>
                    </div>
                    <template x-if="deliveryFee() > 0">
                        <div class="flex justify-between text-muted">
                            <span>Envio</span>
                            <span class="text-ink price-tag" x-text="formatCurrency(deliveryFee())"></span>
                        </div>
                    </template>
                    <div class="ink-divider !my-2"></div>
                    <div class="flex justify-between items-baseline">
                        <span class="font-display font-bold text-lg">Total</span>
                        <span class="price-tag text-2xl text-gold" x-text="formatCurrency(grandTotal())"></span>
                    </div>
                    <?php if ($minOrder > 0): ?>
                    <p class="text-[11px] text-muted pt-2">Pedido minimo: <?= e($currency) ?> <?= number_format($minOrder, 2) ?></p>
                    <?php endif; ?>
                </div>

                <p x-show="error" x-text="error" x-transition class="text-sm text-ember rounded-lg px-3 py-2.5" style="background: rgba(255,107,53,.12); border: 1px solid rgba(255,107,53,.3);"></p>
            </div>
        </template>
    </div>

    <footer class="drawer-footer px-5 sm:px-6 pt-4 sm:pt-5 flex-shrink-0" style="border-top: 1px solid rgba(212,165,116,.12); background: rgba(19,16,13,.6);" x-show="totalQty() > 0">
        <button @click="checkout()" :disabled="loading" class="wa-btn w-full py-3.5 sm:py-4 rounded-2xl font-bold text-sm sm:text-base flex items-center justify-center gap-2 sm:gap-2.5 disabled:opacity-50 disabled:cursor-not-allowed">
            <template x-if="!loading">
                <span class="flex items-center gap-2 sm:gap-2.5">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.371-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>Confirmar por WhatsApp</span>
                </span>
            </template>
            <template x-if="loading">
                <span class="flex items-center gap-2"><svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Procesando...</span>
            </template>
        </button>
        <p class="text-[10px] sm:text-[11px] text-center text-muted mt-2.5 sm:mt-3 leading-relaxed">Se abrira WhatsApp con tu orden lista. Solo dale enviar para confirmar.</p>
    </footer>
</aside>

<footer class="text-center text-xs text-muted/80 pt-8 pb-12 px-4">
    <div class="ink-divider mx-auto max-w-[100px] mb-5"></div>
    <p class="font-display italic"><?= e($brand) ?> · Pedidos confirmados por WhatsApp</p>
</footer>

<script>
window.MENU_ITEMS = <?= json_encode($itemsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.MENU_ZONES = <?= json_encode(array_map(fn($z) => ['id' => (int) $z['id'], 'fee' => (float) $z['fee']], $zones), JSON_UNESCAPED_UNICODE) ?>;
window.MENU_CHECKOUT_URL = <?= json_encode(url('/m/' . $tenant['uuid'] . '/checkout')) ?>;
window.MENU_CURRENCY = <?= json_encode($currency) ?>;
window.MENU_MIN_ORDER = <?= json_encode((float) $minOrder) ?>;
window.WA_PHONE = <?= json_encode($waPhone) ?>;

// Plugin x-intersect minimal (Alpine no lo trae built-in en CDN sin plugin extra)
document.addEventListener('alpine:init', () => {
    Alpine.directive('intersect', (el, { expression }, { evaluate }) => {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) evaluate(expression); });
        }, { threshold: 0.35, rootMargin: '-30% 0px -50% 0px' });
        observer.observe(el);
    });
});

function menuApp() {
    return {
        cart: {},
        cartOpen: false,
        loading: false,
        error: '',
        bumpCart: false,
        activeCategory: 0,
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
                const customer = localStorage.getItem('kp_cust_<?= e($tenant['uuid']) ?>');
                if (customer) this.customer = { ...this.customer, ...JSON.parse(customer) };
            } catch (e) {}
            this.$watch('cart', v => {
                try { localStorage.setItem('kp_cart_<?= e($tenant['uuid']) ?>', JSON.stringify(v)); } catch (e) {}
            });
            this.$watch('customer', v => {
                try { localStorage.setItem('kp_cust_<?= e($tenant['uuid']) ?>', JSON.stringify(v)); } catch (e) {}
            }, { deep: true });
        },
        add(id) {
            const item = window.MENU_ITEMS[id];
            if (!item) return;
            const cur = this.cart[id]?.qty || 0;
            this.cart = { ...this.cart, [id]: { id, qty: cur + 1 } };
            this.bumpCart = true;
            setTimeout(() => this.bumpCart = false, 450);
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
        totalQty() { return Object.values(this.cart).reduce((a, b) => a + (b.qty || 0), 0); },
        lines() {
            return Object.values(this.cart).map(c => {
                const it = window.MENU_ITEMS[c.id];
                if (!it) return null;
                return { ...it, qty: c.qty };
            }).filter(Boolean);
        },
        subtotal() { return this.lines().reduce((acc, l) => acc + (l.price * l.qty), 0); },
        deliveryFee() {
            if (this.customer.delivery_type !== 'delivery') return 0;
            const z = window.MENU_ZONES.find(x => String(x.id) === String(this.customer.delivery_zone_id));
            return z ? z.fee : 0;
        },
        grandTotal() { return this.subtotal() + this.deliveryFee(); },
        formatCurrency(n) { return window.MENU_CURRENCY + ' ' + Number(n).toFixed(2); },
        async checkout() {
            this.error = '';
            if (!this.customer.name.trim()) { this.error = 'Falta tu nombre.'; return; }
            if (!this.customer.phone.trim()) { this.error = 'Falta tu numero de WhatsApp.'; return; }
            if (this.customer.delivery_type === 'delivery' && !this.customer.address.trim()) {
                this.error = 'Falta la direccion de entrega.'; return;
            }
            if (window.MENU_MIN_ORDER > 0 && this.subtotal() < window.MENU_MIN_ORDER) {
                this.error = 'Pedido minimo: ' + this.formatCurrency(window.MENU_MIN_ORDER);
                return;
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
