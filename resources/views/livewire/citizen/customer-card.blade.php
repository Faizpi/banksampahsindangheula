<x-slot:title>Kartu nasabah</x-slot:title>
<x-slot:context>Nomor dan QR untuk layanan bank sampah</x-slot:context>

<section aria-labelledby="customer-card-title" class="space-y-5">
    {{-- Page header --}}
    <div class="flex flex-col gap-5 rounded-2xl border border-border bg-surface p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="space-y-2">
            <p class="text-label font-semibold text-forest-700">Identitas warga</p>
            <h1 id="customer-card-title" class="text-h2 font-bold text-deep-green sm:text-h1">Kartu Digital Nasabah</h1>
            <p class="max-w-xl text-body text-text-secondary">
                Tunjukkan QR ini kepada petugas saat transaksi setoran atau penjemputan. QR aman &amp; tidak memuat informasi pribadi peka.
            </p>
        </div>
        <div class="flex shrink-0 items-center justify-center">
            <x-ui.mascot variant="5" bubble="Tunjukkan QR ini ke petugas ya!" bubblePosition="top"
                class="h-28 w-auto sm:h-32" />
        </div>
    </div>

    @if ($available)
        <div class="mx-auto w-full max-w-[22rem]">
            <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                {{-- Card header --}}
                <div class="flex items-center justify-between gap-3 border-b border-border bg-success-bg/60 px-4 py-3.5 sm:px-5">
                    <div class="min-w-0">
                        <div>
                            <p class="text-caption font-semibold uppercase tracking-wider text-forest-700">Kartu nasabah resmi</p>
                            <p class="truncate text-body-sm font-bold text-deep-green">Bank Sampah Sindangheula</p>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-forest-600/30 bg-surface px-2.5 py-1 text-caption font-semibold text-forest-700">
                        <span class="size-1.5 rounded-full bg-forest-600"></span>
                        Aktif
                    </span>
                </div>

                {{-- QR Code --}}
                <div class="px-4 py-4 sm:px-5">
                    <x-ui.qr-display
                        compact
                        title="QR Nasabah Warga"
                        :context="$qrImageSrc ? 'Pindai untuk verifikasi identitas nasabah.' : 'QR belum diaktifkan untuk kartu ini. Gunakan nomor alternatif di bawah atau minta petugas mengaktifkan QR.'"
                        :image-src="$qrImageSrc"
                        image-alt="Kode QR nasabah"
                        :masked-reference="$maskedNumber"
                        :fallback-number="$maskedNumber"
                        empty-message="QR belum diaktifkan"
                    />
                </div>

                {{-- Card footer --}}
                <div class="flex items-center justify-between gap-3 border-t border-border bg-warm-canvas px-4 py-3 text-caption text-text-secondary sm:px-5">
                    <span>Privasi terjaga · QR tanpa saldo</span>
                    <span class="amount-tabular shrink-0 font-medium text-deep-green">{{ $maskedNumber }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" bubble="Nomor nasabah segera diterbitkan!" bubblePosition="bottom" class="mx-auto h-24 w-auto" />
            <p class="mt-8 text-label font-bold text-deep-green">Kartu Belum Tersedia</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Petugas atau pengelola akan menerbitkan identitas digital setelah verifikasi warga selesai.</p>
        </div>
    @endif
</section>
