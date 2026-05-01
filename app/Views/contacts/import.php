<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<a href="<?= url('/contacts') ?>" class="text-xs flex items-center gap-1 mb-3" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a contactos
</a>

<form action="<?= url('/contacts/import') ?>" method="POST" enctype="multipart/form-data" class="form-shell with-aside" id="importForm">
    <?= csrf_field() ?>

    <div>
        <!-- Hero -->
        <div class="form-hero mb-4">
            <div class="form-hero-icon">📥</div>
            <div class="relative z-10">
                <h1 class="text-xl font-black tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Importar contactos desde CSV</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Sube cientos o miles de contactos a la vez. Detectamos duplicados por email/teléfono y los actualizamos.</p>
            </div>
        </div>

        <!-- Section 1: Archivo -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon">📂</div>
                <div>
                    <div class="form-section-title">Archivo CSV</div>
                    <div class="form-section-sub">UTF-8, primera fila como encabezado de columnas.</div>
                </div>
            </div>

            <label class="block rounded-2xl p-10 text-center cursor-pointer transition-colors hover:border-violet-500/50" style="border: 2px dashed var(--color-border-default); background: var(--color-bg-subtle);" id="dropZone">
                <div class="text-6xl mb-3" id="dropIcon">📂</div>
                <h3 class="font-bold text-base mb-1" style="color: var(--color-text-primary);">Click para seleccionar o arrastra aquí</h3>
                <p class="text-xs mb-4" style="color: var(--color-text-tertiary);">CSV hasta 10 MB · UTF-8 · primera fila = encabezados</p>
                <input type="file" name="csv" accept=".csv,text/csv" required class="hidden" id="fileInput">
                <div id="fileChosen" class="hidden mt-2 text-sm font-semibold" style="color: var(--color-primary);"></div>
            </label>
        </div>

        <!-- Section 2: Headers -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="form-section-icon" style="background: rgba(6,182,212,.12); color:#06B6D4;">📋</div>
                <div>
                    <div class="form-section-title">Encabezados aceptados</div>
                    <div class="form-section-sub">Las columnas con estos nombres se mapean automáticamente.</div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-2 text-xs">
                <?php foreach ([
                    ['first_name', 'Nombre', '👤'],
                    ['last_name', 'Apellido', '👤'],
                    ['company', 'Empresa', '🏢'],
                    ['email', 'Email', '✉'],
                    ['phone', 'Teléfono', '📞'],
                    ['whatsapp', 'WhatsApp', '💬'],
                    ['city', 'Ciudad', '🏙'],
                    ['country', 'País (ISO 2)', '🌍'],
                    ['source', 'Fuente', '🎯'],
                    ['status', 'Estado', '⚡'],
                    ['notes', 'Notas', '📝'],
                ] as [$key, $lbl, $emoji]): ?>
                <div class="flex items-center gap-2 p-2 rounded-lg" style="background: var(--color-bg-subtle);">
                    <span><?= $emoji ?></span>
                    <code class="font-mono text-[11px]" style="color: var(--color-primary);"><?= e($key) ?></code>
                    <span style="color: var(--color-text-tertiary);">→ <?= e($lbl) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <p class="field-help mt-3">También aceptamos variantes en español: <code class="kbd">nombre</code>, <code class="kbd">apellido</code>, <code class="kbd">empresa</code>, <code class="kbd">correo</code>, <code class="kbd">telefono</code>, <code class="kbd">pais</code>.</p>
        </div>

        <div class="form-actions elevated mt-4">
            <a href="<?= url('/contacts') ?>" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit" disabled id="submitBtn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Importar
            </button>
        </div>
    </div>

    <aside class="space-y-3">
        <div class="form-tip">
            <div class="form-tip-title">💡 Antes de importar</div>
            <ul class="text-xs space-y-1 list-disc pl-4">
                <li>Guarda el archivo como <strong>CSV UTF-8</strong> (no Excel)</li>
                <li>Usa <strong>coma</strong> como separador, no punto y coma</li>
                <li>Teléfonos en formato E.164: <code class="kbd">+1 809...</code></li>
                <li>Si un email/teléfono ya existe, se <strong>actualiza</strong></li>
            </ul>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(6,182,212,.04)); border-color: rgba(16,185,129,.2);">
            <div class="form-tip-title" style="color:#34D399;">📥 Ejemplo CSV</div>
            <code class="block text-[10px] font-mono p-2 rounded bg-black/30 overflow-x-auto whitespace-pre" style="color:#34D399;">first_name,last_name,phone,whatsapp
Juan,Perez,+18091234567,+18091234567
Maria,Lopez,,+18095555555</code>
        </div>

        <div class="form-tip" style="background: linear-gradient(135deg, rgba(124,58,237,.06), rgba(168,85,247,.04)); border-color: rgba(124,58,237,.2);">
            <div class="form-tip-title">🤖 Después de importar</div>
            <p>Cuando los contactos te escriban por WhatsApp, la IA tendrá su contexto y los saludará por su nombre.</p>
        </div>
    </aside>
</form>

<script>
(function () {
    const input = document.getElementById('fileInput');
    const dropZone = document.getElementById('dropZone');
    const chosen = document.getElementById('fileChosen');
    const icon = document.getElementById('dropIcon');
    const submitBtn = document.getElementById('submitBtn');

    function showFile(f) {
        chosen.textContent = '✓ ' + f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
        chosen.classList.remove('hidden');
        icon.textContent = '✅';
        submitBtn.disabled = false;
    }

    input.addEventListener('change', e => {
        if (e.target.files[0]) showFile(e.target.files[0]);
    });

    ['dragover','dragenter'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.style.borderColor = '#7C3AED';
            dropZone.style.background = 'rgba(124,58,237,.08)';
        });
    });
    ['dragleave','drop'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.style.borderColor = '';
            dropZone.style.background = '';
        });
    });
    dropZone.addEventListener('drop', e => {
        if (e.dataTransfer.files[0]) {
            input.files = e.dataTransfer.files;
            showFile(e.dataTransfer.files[0]);
        }
    });
})();
</script>
<?php \App\Core\View::stop(); ?>
