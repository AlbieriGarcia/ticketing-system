{{-- Cuenta regresiva en el navegador; al llegar a cero refresca la tabla para que la reserva vencida desaparezca --}}
<div
    x-data="{ seconds: {{ max(0, (int) now()->diffInSeconds($getState(), false)) }} }"
    x-init="const timer = setInterval(() => { if (--seconds <= 0) { clearInterval(timer); $wire.$refresh() } }, 1000)"
    x-bind:style="{ color: seconds < 60 ? 'var(--danger-600)' : 'inherit' }"
    x-text="String(Math.floor(Math.max(seconds, 0) / 60)).padStart(2, '0') + ':' + String(Math.max(seconds, 0) % 60).padStart(2, '0')"
    style="padding: 0 0.75rem; font-variant-numeric: tabular-nums; font-weight: 600"
></div>
