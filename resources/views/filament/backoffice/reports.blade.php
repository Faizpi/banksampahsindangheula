<x-filament-panels::page>
    <section class="rounded-xl border border-primary-200 bg-primary-950 p-5 text-white shadow-sm sm:p-6" aria-labelledby="backoffice-reports-title">
        <p class="text-sm font-semibold text-primary-200">Laporan operasional</p>
        <h2 id="backoffice-reports-title" class="mt-1 text-2xl font-bold">Ringkasan transaksi dan ekspor Excel</h2>
        <p class="mt-2 max-w-3xl text-sm text-primary-100">Gunakan filter periode dan scope untuk membaca data. Halaman ini read-only; perubahan transaksi tetap dilakukan melalui resource dan action resminya.</p>
    </section>

    <livewire:treasurer.reports />
</x-filament-panels::page>
