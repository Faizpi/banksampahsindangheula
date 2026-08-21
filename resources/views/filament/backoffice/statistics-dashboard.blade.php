<x-filament-panels::page>
    <div class="space-y-6">
        <section class="backoffice-page-intro" aria-labelledby="statistics-dashboard-title">
            <p class="text-sm font-semibold text-forest-700">Pengawasan berbasis data</p>
            <h2 id="statistics-dashboard-title" class="mt-1 text-2xl font-bold text-deep-green">Statistik internal</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">Tinjau indikator operasional sesuai cakupan akses Anda. Metrik dengan jumlah subjek di bawah ambang privasi tetap disamarkan.</p>
        </section>

        <section aria-labelledby="statistics-dashboard-content-title">
            <h3 id="statistics-dashboard-content-title" class="sr-only">Filter dan metrik statistik internal</h3>
            <livewire:statistics.internal-dashboard />
        </section>
    </div>
</x-filament-panels::page>
