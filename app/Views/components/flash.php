<?php
$success = flash('success');
$error   = flash('error');
$warning = flash('warning');
$info    = flash('info');
?>
<?php if ($success): ?>
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="mb-4 p-3.5 rounded-xl flex items-start gap-3" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); color: #34D399;">
    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    </div>
    <div class="flex-1 text-sm pt-1.5"><?= e((string) $success) ?></div>
    <button @click="show = false" class="opacity-60 hover:opacity-100 p-1">&times;</button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)" x-transition class="mb-4 p-3.5 rounded-xl flex items-start gap-3" style="background: rgba(244, 63, 94, 0.08); border: 1px solid rgba(244, 63, 94, 0.2); color: #FB7185;">
    <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
    </div>
    <div class="flex-1 text-sm pt-1.5"><?= e((string) $error) ?></div>
    <button @click="show = false" class="opacity-60 hover:opacity-100 p-1">&times;</button>
</div>
<?php endif; ?>

<?php if ($warning): ?>
<div class="mb-4 p-3.5 rounded-xl flex items-start gap-3" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); color: #FBBF24;">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
    <div class="flex-1 text-sm"><?= e((string) $warning) ?></div>
</div>
<?php endif; ?>

<?php if ($info): ?>
<div class="mb-4 p-3.5 rounded-xl flex items-start gap-3" style="background: rgba(6, 182, 212, 0.08); border: 1px solid rgba(6, 182, 212, 0.2); color: #22D3EE;">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
    <div class="flex-1 text-sm"><?= e((string) $info) ?></div>
</div>
<?php endif; ?>
