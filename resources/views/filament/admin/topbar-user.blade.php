{{-- resources/views/filament/admin/topbar-user.blade.php --}}
{{-- Tampil di navbar, sebelum tombol avatar (user menu) --}}
<div class="hidden sm:flex flex-col items-end leading-tight mr-1 select-none">
    <span class="text-sm font-semibold text-slate-900">
        {{ auth()->user()->name ?? 'Admin' }}
    </span>
    <span class="text-xs text-slate-400">
        {{ auth()->user()->role ?? 'Admin' }}
    </span>
</div>