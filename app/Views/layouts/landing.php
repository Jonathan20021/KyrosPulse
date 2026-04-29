<?php /** @var array $data */ ?>
<!DOCTYPE html>
<html lang="es" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e((string) config('app.name', 'Kyros Pulse')) ?> — La plataforma SaaS todo-en-uno para CRM, WhatsApp e IA</title>
    <meta name="description" content="Centraliza CRM, WhatsApp multiagente, automatizaciones e inteligencia artificial Claude Sonnet 6 en un solo panel. Para empresas que venden mas y atienden mejor.">
    <meta name="theme-color" content="#0A0F25">

    <meta property="og:title" content="<?= e((string) config('app.name')) ?> — CRM + WhatsApp + IA">
    <meta property="og:description" content="La plataforma SaaS premium para gestionar clientes, ventas y conversaciones con inteligencia artificial.">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

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
                        primary: '#7C3AED',
                        cyan:    '#06B6D4',
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#050817] text-slate-200 antialiased overflow-x-hidden">
    <?= \App\Core\View::section('content') ?>

<script>
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('in-view');
    });
}, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Animated counters
function animateCounter(el) {
    const target = parseFloat(el.dataset.count);
    const decimals = parseInt(el.dataset.decimals || 0);
    const suffix = el.dataset.suffix || '';
    const duration = 1800;
    const start = performance.now();
    const startVal = 0;

    function tick(now) {
        const elapsed = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - elapsed, 3);
        const value = startVal + (target - startVal) * eased;
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

// Sticky nav background
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    if (!nav) return;
    if (window.scrollY > 20) nav.classList.add('nav-scrolled');
    else nav.classList.remove('nav-scrolled');
}, { passive: true });

// Cursor spotlight on cards
document.querySelectorAll('.spotlight-card').forEach(card => {
    card.addEventListener('mousemove', e => {
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${e.clientX - rect.left}px`);
        card.style.setProperty('--my', `${e.clientY - rect.top}px`);
    });
});
</script>

<style>
.nav-scrolled {
    background: rgba(5, 8, 23, 0.85);
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.spotlight-card { position: relative; overflow: hidden; }
.spotlight-card::before {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(124, 58, 237, 0.15), transparent 70%);
    transform: translate(-50%, -50%);
    left: var(--mx, 50%); top: var(--my, 50%);
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}
.spotlight-card:hover::before { opacity: 1; }
</style>

</body>
</html>
