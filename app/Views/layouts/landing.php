<?php
/** @var array $data */
$brandName     = (string) config('app.name', 'Evallish Pulse');
$pageTitle     = $brandName . ' — Contact center con WhatsApp e IA en una sola bandeja';
$pageDesc      = 'Evallish Pulse: bandeja omnicanal, WhatsApp multiagente, agentes IA, CRM, automatizaciones y reportes en una sola plataforma. Prueba la demo 24 horas sin tarjeta.';
$pageUrl       = url('/');
// OG/Twitter quieren URLs absolutas y predecibles (sin cache-busting ?v=)
// porque las imagenes se cachean del lado del crawler.
$ogImage       = url('/assets/css/og-card.png');
$ogImageLogo   = url('/assets/css/logo.png');
?>
<!DOCTYPE html>
<html lang="es" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="keywords" content="contact center, WhatsApp Business, WhatsApp Cloud API, CRM, agentes IA, automatizaciones, SaaS, multi-canal, bandeja unificada, customer support">
    <meta name="author" content="<?= e($brandName) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#0B1B3F">
    <link rel="canonical" href="<?= e($pageUrl) ?>">

    <link rel="icon" type="image/png" href="<?= asset('css/logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('css/logo.png') ?>">

    <!-- Open Graph (WhatsApp, Facebook, LinkedIn, Slack, Discord) -->
    <meta property="og:site_name"      content="<?= e($brandName) ?>">
    <meta property="og:title"          content="<?= e($brandName) ?> — Contact Center · WhatsApp · IA">
    <meta property="og:description"    content="<?= e($pageDesc) ?>">
    <meta property="og:url"            content="<?= e($pageUrl) ?>">
    <meta property="og:type"           content="website">
    <meta property="og:locale"         content="es_DO">
    <meta property="og:locale:alternate" content="es_ES">
    <meta property="og:locale:alternate" content="es_MX">
    <meta property="og:image"          content="<?= e($ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= e($ogImage) ?>">
    <meta property="og:image:type"     content="image/png">
    <meta property="og:image:width"    content="1200">
    <meta property="og:image:height"   content="630">
    <meta property="og:image:alt"      content="<?= e($brandName) ?> · Contact center con WhatsApp e IA">

    <!-- Twitter / X -->
    <meta name="twitter:card"          content="summary_large_image">
    <meta name="twitter:title"         content="<?= e($brandName) ?> — Contact Center · WhatsApp · IA">
    <meta name="twitter:description"   content="<?= e($pageDesc) ?>">
    <meta name="twitter:image"         content="<?= e($ogImage) ?>">
    <meta name="twitter:image:alt"     content="<?= e($brandName) ?> · Contact center con WhatsApp e IA">

    <!-- Structured data (Google rich results) -->
    <script type="application/ld+json"><?= json_encode([
        '@context'      => 'https://schema.org',
        '@type'         => 'SoftwareApplication',
        'name'          => $brandName,
        'description'   => $pageDesc,
        'url'           => $pageUrl,
        'image'         => $ogImage,
        'logo'          => $ogImageLogo,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem'     => 'Web',
        'offers' => [
            '@type'         => 'Offer',
            'price'         => '0',
            'priceCurrency' => 'USD',
            'description'   => 'Demo gratuita 24 horas sin tarjeta de credito.',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name'  => $brandName,
            'logo'  => ['@type' => 'ImageObject', 'url' => $ogImageLogo],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                    colors: {
                        primary:   '#2563EB',
                        primaryDk: '#1D4ED8',
                        primaryLt: '#3B82F6',
                        accent:    '#1E40AF',
                        sky:       '#0EA5E9',
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --ep-blue-50:  #EFF6FF;
            --ep-blue-100: #DBEAFE;
            --ep-blue-300: #93C5FD;
            --ep-blue-400: #60A5FA;
            --ep-blue-500: #3B82F6;
            --ep-blue-600: #2563EB;
            --ep-blue-700: #1D4ED8;
            --ep-blue-800: #1E40AF;
            --ep-blue-900: #1E3A8A;
            --ep-bg:       #060A18;
            --ep-bg-2:     #0A1124;
        }
        body { background: var(--ep-bg); }

        .ep-grad-text {
            background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 50%, #2563EB 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .ep-btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem 1.25rem; border-radius: .85rem;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 60%, #1D4ED8 100%);
            color: #fff; font-weight: 600; font-size: .9rem;
            box-shadow: 0 10px 30px -8px rgba(37,99,235,.55), inset 0 1px 0 rgba(255,255,255,.18);
            border: 1px solid rgba(96,165,250,.4);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }
        .ep-btn-primary:hover { transform: translateY(-1px); filter: brightness(1.08); box-shadow: 0 14px 38px -8px rgba(37,99,235,.7); }
        .ep-btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem 1.25rem; border-radius: .85rem;
            background: rgba(255,255,255,.05);
            color: #E2E8F0; font-weight: 600; font-size: .9rem;
            border: 1px solid rgba(255,255,255,.10);
            backdrop-filter: blur(8px);
            transition: all .15s ease;
        }
        .ep-btn-secondary:hover { background: rgba(255,255,255,.08); border-color: rgba(96,165,250,.35); color:#fff; }
        .ep-btn-ghost {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .55rem .9rem; border-radius: .65rem;
            color:#CBD5E1; font-weight: 500; font-size: .85rem;
            transition: all .15s ease;
        }
        .ep-btn-ghost:hover { background: rgba(255,255,255,.05); color:#fff; }
        .ep-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .35rem .75rem; border-radius: 999px;
            border: 1px solid rgba(96,165,250,.25);
            background: rgba(37,99,235,.10);
            color: #93C5FD;
            font-size: .72rem; font-weight: 600;
            letter-spacing: .05em;
        }
        .ep-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.035), rgba(255,255,255,0.015));
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 1.1rem;
            transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ep-card:hover {
            border-color: rgba(96,165,250,.35);
            background: linear-gradient(180deg, rgba(37,99,235,.06), rgba(37,99,235,.02));
            transform: translateY(-2px);
        }
        .ep-feature-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.08));
            border: 1px solid rgba(96,165,250,.30);
            color: #93C5FD;
        }
        .ep-mesh {
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(37,99,235,.18), transparent 60%),
                radial-gradient(ellipse 70% 50% at 80% 30%, rgba(14,165,233,.12), transparent 60%),
                radial-gradient(ellipse 70% 50% at 10% 60%, rgba(99,102,241,.10), transparent 60%);
        }
        .ep-grid {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 56px 56px;
        }
        .ep-grid-fade {
            mask-image: radial-gradient(ellipse 60% 60% at 50% 40%, #000 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse 60% 60% at 50% 40%, #000 30%, transparent 80%);
        }
        .ep-glow {
            box-shadow:
                0 0 0 1px rgba(96,165,250,.25),
                0 30px 80px -20px rgba(37,99,235,.35),
                0 12px 40px -8px rgba(0,0,0,.6);
        }
        .ep-logo-bubble {
            background: #FFFFFF;
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 6px;
            box-shadow: 0 10px 28px -6px rgba(37,99,235,.45);
        }
        .ep-live-dot {
            display:inline-block;
            width:8px; height:8px; border-radius:50%;
            background:#3B82F6;
            box-shadow:0 0 0 0 rgba(59,130,246,.65);
            animation: epPulse 1.8s infinite;
        }
        @keyframes epPulse {
            0%   { box-shadow:0 0 0 0 rgba(59,130,246,.65); }
            70%  { box-shadow:0 0 0 12px rgba(59,130,246,0); }
            100% { box-shadow:0 0 0 0 rgba(59,130,246,0); }
        }
        .ep-float-anim { animation: epFloat 6s ease-in-out infinite; }
        @keyframes epFloat {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }
        .reveal { opacity: 0; transform: translateY(18px); transition: opacity .65s ease, transform .65s cubic-bezier(.16,1,.3,1); }
        .reveal.in-view { opacity: 1; transform: translateY(0); }
        .reveal-d1 { transition-delay: .08s; }
        .reveal-d2 { transition-delay: .16s; }
        .reveal-d3 { transition-delay: .24s; }

        .nav-scrolled {
            background: rgba(6, 10, 24, 0.88);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .spotlight-card { position: relative; overflow: hidden; }
        .spotlight-card::before {
            content: '';
            position: absolute;
            width: 460px; height: 460px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,.18), transparent 70%);
            transform: translate(-50%, -50%);
            left: var(--mx, 50%); top: var(--my, 50%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .spotlight-card:hover::before { opacity: 1; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-slate-200 antialiased overflow-x-hidden">
    <?= \App\Core\View::section('content') ?>

<script>
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('in-view');
    });
}, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

function animateCounter(el) {
    const target = parseFloat(el.dataset.count);
    const decimals = parseInt(el.dataset.decimals || 0);
    const suffix = el.dataset.suffix || '';
    const duration = 1800;
    const start = performance.now();
    function tick(now) {
        const elapsed = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - elapsed, 3);
        const value = target * eased;
        el.textContent = (decimals > 0 ? value.toFixed(decimals) : Math.round(value)).toLocaleString('es-ES') + suffix;
        if (elapsed < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}
const counterObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('[data-count]').forEach(el => counterObserver.observe(el));

const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    if (!nav) return;
    if (window.scrollY > 20) nav.classList.add('nav-scrolled');
    else nav.classList.remove('nav-scrolled');
}, { passive: true });

document.querySelectorAll('.spotlight-card').forEach(card => {
    card.addEventListener('mousemove', e => {
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${e.clientX - rect.left}px`);
        card.style.setProperty('--my', `${e.clientY - rect.top}px`);
    });
});
</script>

</body>
</html>
