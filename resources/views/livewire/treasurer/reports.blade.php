<x-slot:title>Laporan setoran</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section class="space-y-6" aria-labelledby="reports-title">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pengawasan Bendahara</p>
            <h1 id="reports-title" class="mt-1 text-h1 font-bold text-deep-green">Laporan Setoran</h1>
            <p class="mt-2 text-body text-text-secondary">Angka memakai scope transaksi final dan definisi yang sama dengan rekonsiliasi.</p>
        </div>
        <x-ui.mascot variant="5" class="hidden h-20 w-auto sm:block" />
    </div>

    {{-- Metric Cards --}}
    <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
            <p class="text-caption font-medium text-text-secondary">Nasabah</p>
            <strong class="mt-2 block amount-tabular text-h2 font-bold text-deep-green">{{ $metrics['subject_count'] }}</strong>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
            <p class="text-caption font-medium text-text-secondary">Setoran</p>
            <strong class="mt-2 block amount-tabular text-h2 font-bold text-deep-green">{{ $metrics['deposit_count'] }}</strong>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
            <p class="text-caption font-medium text-text-secondary">Total Berat</p>
            <strong class="mt-2 block amount-tabular text-h2 font-bold text-deep-green">{{ $metrics['total_weight_kg'] }} <span class="text-label font-semibold text-text-secondary">kg</span></strong>
        </div>
        <div class="rounded-xl border border-forest-600/30 bg-success-bg p-4 shadow-xs">
            <p class="text-caption font-medium text-forest-700">Total Nilai</p>
            <strong class="mt-2 block amount-tabular text-h2 font-bold text-forest-700">Rp {{ number_format($metrics['total_value'], 0, ',', '.') }}</strong>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
            <p class="text-caption font-medium text-text-secondary">Plastik</p>
            <strong class="mt-2 block amount-tabular text-h2 font-bold text-deep-green">{{ $metrics['plastic_weight_kg'] }} <span class="text-label font-semibold text-text-secondary">kg</span></strong>
        </div>
    </div>

    {{-- Filter Form --}}
    <x-ui.panel title="Filter periode" description="Terapkan rentang tanggal untuk menyesuaikan laporan.">
        <form wire:submit="refreshReport" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filter laporan">
            <x-ui.select wire:model="reportType" name="reportType" label="Jenis laporan" :options="$reportTypes" />
            <x-ui.input wire:model="start" name="start" label="Mulai" type="date" />
            <x-ui.input wire:model="end" name="end" label="Sampai (eksklusif)" type="date" />
            <x-ui.input wire:model="serviceAreaId" name="serviceAreaId" label="ID area (opsional)" type="number" min="1" />
            <x-ui.input wire:model="status" name="status" label="Status (opsional)" />
            <x-ui.input wire:model="search" name="search" label="Cari nomor/scope" />
            <x-ui.button type="submit" wire:loading.attr="disabled" class="lg:self-end">
                <span wire:loading.remove>Terapkan</span>
                <span wire:loading>Memuat...</span>
            </x-ui.button>
        </form>
    </x-ui.panel>

    {{-- Export --}}
    <x-ui.panel title="Ekspor laporan" description="Unduh data yang sesuai filter aktif dalam format yang Anda pilih.">
        <div class="flex flex-wrap items-end gap-3">
            <x-ui.select wire:model="format" label="Format ekspor" name="format" :options="['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF']" />
            <x-ui.button wire:click="export" type="button" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="export">
                    <svg viewBox="0 0 24 24" class="mr-2 inline size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Ekspor Privat
                </span>
                <span wire:loading wire:target="export">Menyiapkan...</span>
            </x-ui.button>
        </div>
    </x-ui.panel>

    <x-ui.panel title="Hasil laporan" description="Read-only. Data mengikuti scope dan filter yang diterapkan.">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm" aria-label="Hasil laporan">
                <thead class="border-b border-border text-caption text-text-secondary">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Referensi</th>
                        <th class="px-3 py-2 font-semibold">Waktu</th>
                        <th class="px-3 py-2 font-semibold">Subjek</th>
                        <th class="px-3 py-2 font-semibold">Status</th>
                        <th class="px-3 py-2 text-right font-semibold">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-deep-green">{{ $row['reference'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-secondary">{{ $row['date'] }}</td>
                            <td class="px-3 py-2 text-text-secondary">{{ $row['subject_id'] }}</td>
                            <td class="px-3 py-2 text-text-secondary">{{ $row['status'] }}</td>
                            <td class="px-3 py-2 text-right amount-tabular">{{ $row['amount'] === '' ? '—' : 'Rp '.number_format((int) $row['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-text-secondary">Tidak ada data untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.panel>
</section>
