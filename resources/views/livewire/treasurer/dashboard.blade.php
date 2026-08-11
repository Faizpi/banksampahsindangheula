<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<x-slot:todayTasks>
    <x-ui.panel title="Pencairan siap dibayar" description="Hanya pencairan dalam scope Anda yang tampil.">
        @if ($readyPayments->isEmpty())
            <x-ui.empty-state title="Belum ada pencairan siap dibayar" description="Pencairan yang disetujui dan siap dibayar akan muncul di sini." />
        @else
            <div class="mb-4 flex flex-col gap-1 border-b border-border pb-4 sm:flex-row sm:items-baseline sm:justify-between">
                <p class="text-body-sm text-text-secondary"><strong class="text-deep-green">{{ $readyPayments->count() }}</strong> antrean aktif</p>
                <p class="amount-tabular text-title font-bold text-harvest-gold">Rp {{ number_format($readyPaymentTotal, 0, ',', '.') }}</p>
            </div>
            <div class="grid gap-3">
                @foreach ($readyPayments as $withdrawal)
                    <a href="{{ route('treasurer.withdrawal.payments') }}" class="flex min-h-[72px] items-center justify-between gap-3 rounded-md border border-warning-bg bg-warning-bg p-4 transition hover:border-harvest-gold">
                        <span class="min-w-0"><span class="block text-label font-bold text-deep-green">{{ $withdrawal->request_number }}</span><span class="mt-1 block truncate text-body-sm text-text-secondary">{{ $withdrawal->customer?->name ?? 'Nasabah' }} · {{ $withdrawal->pickup_date?->translatedFormat('d M Y') ?? 'Tanggal belum tersedia' }}</span></span>
                        <span class="shrink-0 amount-tabular text-label font-bold text-deep-green">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
            <a href="{{ route('treasurer.withdrawal.payments') }}" class="mt-4 inline-flex min-h-touch items-center gap-2 text-label font-bold text-forest-700 underline underline-offset-4">Buka seluruh antrean pembayaran</a>
        @endif
    </x-ui.panel>
</x-slot:todayTasks>

<div class="grid gap-6">

{{-- Header + Mascot --}}
<section aria-labelledby="treasurer-dashboard-title" class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                <span class="text-caption font-semibold text-harvest-gold uppercase tracking-wide">Bendahara Bank Sampah</span>
            </div>
            <h1 id="treasurer-dashboard-title" class="mt-2 text-h2 font-bold text-deep-green">Selamat bertugas!</h1>
            <p class="mt-1.5 text-body-sm text-text-secondary">
                {{ now()->translatedFormat('l, d F Y') }} — Pembayaran dan rekonsiliasi sesuai akses Anda.
            </p>
        </div>
        <x-ui.mascot variant="12" bubble="Rekap keuangan hari ini!" bubblePosition="top" class="h-24 w-auto shrink-0 sm:h-28" animate />
    </div>
</section>

{{-- Quick Links --}}
<section aria-labelledby="treasurer-actions-title">
    <h2 id="treasurer-actions-title" class="mb-3 text-label font-bold text-text-secondary">Aksi Cepat</h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <a href="{{ route('treasurer.withdrawal.payments') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-harvest-gold hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-warning-bg text-harvest-gold transition-colors group-hover:bg-harvest-gold group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                </svg>
            </div>
            <span class="text-caption font-semibold text-deep-green">Proses Pembayaran</span>
        </a>

        <a href="{{ route('treasurer.reports') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0M9 12h.01M13 12h2M9 16h.01M13 16h2"/>
                </svg>
            </div>
            <span class="text-caption font-semibold text-deep-green">Laporan &amp; Ekspor</span>
        </a>

        <a href="{{ route('profile.password') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-border hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-disabled-bg text-text-secondary transition-colors group-hover:bg-warm-canvas">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
            </div>
            <span class="text-caption font-semibold text-deep-green">Profil Akun</span>
        </a>

        @if ($canViewStatistics)
            <a href="{{ route('statistics.internal') }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-sky-blue hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-info-bg text-sky-blue transition-colors group-hover:bg-sky-blue group-hover:text-white">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 5-7"/></svg>
                </div>
                <span class="text-caption font-semibold text-deep-green">Statistik Internal</span>
            </a>
        @endif
    </div>
</section>

{{-- Panduan --}}
<section>
    <x-ui.panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-ui.mascot variant="5" class="mx-auto h-20 w-auto sm:mx-0 sm:shrink-0" />
            <div>
                <p class="text-label font-semibold text-harvest-gold">Alur Pembayaran</p>
                <h2 class="mt-1 text-title font-bold text-deep-green">Verifikasi → Konfirmasi → Bukti</h2>
                <p class="mt-1.5 text-body-sm text-text-secondary">
                    Pastikan identitas penerima dan nominal sesuai sebelum pembayaran final. Bukti tersimpan otomatis untuk rekonsiliasi.
                </p>
                <a href="{{ route('treasurer.withdrawal.payments') }}" class="mt-3 inline-flex min-h-touch items-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green transition hover:opacity-90">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Lihat Pencairan
                </a>
            </div>
        </div>
    </x-ui.panel>
</section>

</div>{{-- /grid gap-6 (Livewire single root) --}}
