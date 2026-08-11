<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-primary-200 bg-primary-950 p-5 text-white shadow-sm sm:p-6" aria-labelledby="work-queue-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-primary-200">Admin operasional</p>
                    <h2 id="work-queue-heading" class="mt-1 text-2xl font-bold">Pusat perhatian hari ini</h2>
                    <p class="mt-2 max-w-2xl text-sm text-primary-100">Prioritaskan antrean yang memengaruhi layanan warga dan saldo.</p>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:text-right"><div><dt class="text-primary-200">Lingkungan</dt><dd class="font-bold">{{ $environment }}</dd></div><div><dt class="text-primary-200">Area aktif</dt><dd class="font-bold">{{ $activeAreas }}</dd></div></dl>
            </div>
            @if ($maintenanceEnabled)
                <p class="mt-5 rounded-lg border border-warning-300 bg-warning-100 px-3 py-2 text-sm font-semibold text-warning-950">Maintenance aktif. Periksa scope sebelum melanjutkan pekerjaan operasional.</p>
            @endif
        </section>

        <section aria-labelledby="attention-counts-title">
            <div class="flex items-end justify-between gap-4"><div><h2 id="attention-counts-title" class="text-xl font-bold text-gray-950">Antrean yang perlu perhatian</h2><p class="mt-1 text-sm text-gray-600">Diperbarui {{ $lastUpdated }} WIB.</p></div></div>
            @if (! $hasVisibleQueues)
                <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700">
                    Tidak ada antrean yang tersedia untuk peran ini.
                </div>
            @elseif (collect($queues)->every(fn (array $queue): bool => $queue['count'] === 0))
                <div class="mt-4 rounded-xl border border-success-200 bg-success-50 p-5 text-sm text-success-900">
                    <strong>Semua antrean prioritas selesai.</strong>
                    <span class="block mt-1">Tidak ada pekerjaan mendesak saat ini.</span>
                </div>
            @endif
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($queues as $queue)
                    <a href="{{ $queue['href'] }}" class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">
                        <div class="flex items-start justify-between gap-3"><span class="text-sm font-semibold text-gray-700">{{ $queue['label'] }}</span><strong class="text-2xl font-bold tabular-nums text-primary-800">{{ $queue['count'] }}</strong></div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $queue['description'] }}</p>
                        <span class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-primary-700 underline underline-offset-4">{{ $queue['cta'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-primary-100 bg-primary-50 p-5" aria-labelledby="decision-guide-title">
            <h2 id="decision-guide-title" class="text-lg font-bold text-primary-950">Urutan review yang aman</h2>
            <ol class="mt-3 grid gap-3 text-sm text-primary-950 sm:grid-cols-3"><li><strong>1. Konteks.</strong> Periksa warga, wilayah, bukti, dan riwayat.</li><li><strong>2. Dampak.</strong> Pastikan kapasitas, saldo, dana ditahan, dan batas waktu sesuai.</li><li><strong>3. Keputusan.</strong> Setujui atau tolak, lalu catat alasannya.</li></ol>
        </section>
    </div>
</x-filament-panels::page>
