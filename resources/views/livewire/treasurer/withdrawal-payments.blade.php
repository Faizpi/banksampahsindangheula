<x-slot:title>Pembayaran pencairan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="withdrawal-payments-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-harvest-gold">Bendahara</p>
            <h1 id="withdrawal-payments-title" class="mt-2 text-h1 font-bold text-deep-green">Pembayaran Pencairan</h1>
            <p class="mt-3 text-body text-text-secondary">
                Pembayaran hanya untuk Bendahara atau petugas yang ditugaskan sebagai payer. Pilih pencairan yang ditugaskan kepada Anda; verifikasi penerima dan bukti wajib sebelum saldo keluar.
            </p>
        </div>
        <x-ui.mascot variant="12" bubble="Bayar tepat waktu!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Pembayaran berhasil" :description="session('success')" />
    @endif

    {{-- Withdrawal List --}}
    @forelse ($withdrawals as $withdrawal)
        <x-ui.panel :title="$withdrawal->request_number" :description="$withdrawal->customer?->name ?? 'Nasabah'">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <p class="amount-tabular text-h2 font-extrabold text-deep-green">
                        Rp{{ number_format($withdrawal->amount, 0, ',', '.') }}
                    </p>
                    <p class="text-body-sm text-text-secondary">
                        {{ $withdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}
                        @if ($withdrawal->pickup_location)
                            · {{ $withdrawal->pickup_location }}
                        @endif
                    </p>
                    <p class="text-body-sm text-text-secondary">Hold saldo: <strong class="text-deep-green">Rp {{ number_format($withdrawal->balanceHold?->amount ?? $withdrawal->amount, 0, ',', '.') }}</strong></p>
                </div>
                <button type="button" wire:click="select({{ $withdrawal->id }})"
                    class="inline-flex min-h-touch shrink-0 items-center justify-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green shadow-xs transition hover:opacity-90">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                    </svg>
                    Proses Pembayaran
                </button>
            </div>
        </x-ui.panel>
    @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="5" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Belum ada pencairan siap bayar</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Pencairan yang disetujui dan ditugaskan kepada Anda akan muncul di sini.</p>
        </div>
    @endforelse

    {{-- Verification Form --}}
    @if ($selectedWithdrawal !== null)
        @php
            $isCardVerification = $recipientVerification === 'kartu_nasabah';
            $verificationHint = $isCardVerification
                ? 'Kartu nasabah adalah media identitas fisik atau digital. Periksa kartu yang ditunjukkan warga, lalu cocokkan nama dan nomor yang tercetak.'
                : 'Nomor nasabah adalah kode unik warga. Minta warga menyebutkan nomornya, lalu cocokkan dengan nama penerima pada ringkasan.';
        @endphp
        <x-ui.panel wire:key="withdrawal-payment-{{ $selectedWithdrawal->id }}" title="Verifikasi dan bukti pembayaran" description="Pastikan nama penerima dan nominal sesuai sebelum pembayaran dicatat." state="success">
            <div class="grid gap-4">
                <dl class="grid gap-3 rounded-md bg-warm-canvas p-4 text-body-sm sm:grid-cols-2">
                    <div><dt class="text-text-secondary">Penerima</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ $selectedWithdrawal->customer?->name ?? 'Nasabah' }}</dd></div>
                    <div><dt class="text-text-secondary">Nominal</dt><dd class="mt-1 amount-tabular text-title font-bold text-deep-green">Rp {{ number_format($selectedWithdrawal->amount, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-text-secondary">Saldo tersedia sebelum bayar</dt><dd class="mt-1 amount-tabular font-bold text-forest-700">{{ $availableBalance === null ? 'Akan dicek saat pembayaran' : 'Rp '.number_format($availableBalance, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-text-secondary">Saldo ditahan</dt><dd class="mt-1 amount-tabular font-bold text-harvest-gold">Rp {{ number_format($selectedWithdrawal->balanceHold?->amount ?? $selectedWithdrawal->amount, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-text-secondary">Tanggal pengambilan</dt><dd class="mt-1 font-semibold text-deep-green">{{ $selectedWithdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-text-secondary">Batas pengambilan</dt><dd class="mt-1 font-semibold text-deep-green">{{ $selectedWithdrawal->expires_at?->translatedFormat('d F Y, H:i') ?? 'Belum tersedia' }}</dd></div>
                </dl>
                <x-ui.select wire:model.live="recipientVerification" label="Cara verifikasi penerima" name="recipientVerification"
                    hint="{{ $verificationHint }}"
                    :options="['kartu_nasabah' => 'Kartu nasabah', 'nomor_nasabah' => 'Nomor nasabah']" />
                <x-ui.input wire:model="recipientReference" label="Nomor nasabah yang diverifikasi" name="recipientReference"
                    placeholder="Masukkan CST-########"
                    hint="Masukkan nomor yang tercetak pada kartu atau yang disebutkan warga. Jangan isi nomor pengajuan pencairan."
                    :error="$errors->first('recipientReference')" />
                <div class="rounded-md border border-info-bg bg-info-bg p-4 text-body-sm text-text-primary" role="note">
                    <p class="font-semibold text-deep-green">Kartu dan nomor nasabah berbeda</p>
                    <p class="mt-1">Kartu adalah media identitasnya; nomor nasabah adalah kode unik di dalam kartu. Pilihan di atas mencatat cara Anda memeriksa penerima, sedangkan field berikut mencatat kode yang dicocokkan sistem.</p>
                </div>
                <div wire:key="payment-proof-picker-{{ $selectedWithdrawal->id }}" class="rounded-md border border-border bg-warm-canvas p-4" data-photo-picker data-photo-picker-max="1" data-photo-picker-limit="1048576">
                    <div>
                        <h3 class="text-label font-bold text-deep-green">Satu foto bukti pembayaran</h3>
                        <p class="mt-1 text-body-sm text-text-secondary">Ambil foto melalui kamera atau pilih satu foto dari galeri. Foto akan dikompres menjadi JPEG maksimal 1 MB.</p>
                    </div>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <button type="button" data-photo-picker-trigger="camera" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label font-semibold text-deep-green transition hover:border-forest-600 hover:bg-success-bg focus:outline-none focus:ring-2 focus:ring-focus">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L8 6H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3l-1.5-2Z"/><circle cx="12" cy="12.5" r="3.25"/></svg>
                            Ambil dari kamera
                        </button>
                        <button type="button" data-photo-picker-trigger="gallery" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-border bg-surface px-5 text-label font-semibold text-deep-green transition hover:border-forest-600 hover:bg-success-bg focus:outline-none focus:ring-2 focus:ring-focus">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-4.5-4.5L7 20"/></svg>
                            Pilih dari galeri
                        </button>
                    </div>
                    <input id="payment-proof" wire:model="proof" type="file" accept="image/jpeg,image/png" data-photo-picker-input class="mt-3 block min-h-touch w-full rounded-md border-2 border-dashed border-border bg-surface p-4 text-body text-text-secondary transition hover:border-forest-600 focus:outline-none focus:ring-2 focus:ring-focus">
                    <p data-photo-picker-status class="mt-2 text-body-sm text-text-secondary" aria-live="polite">Belum ada foto dipilih.</p>
                    <div data-photo-picker-preview class="mt-2 grid gap-2" aria-live="polite"></div>
                    <div wire:loading wire:target="proof" class="mt-2 text-body-sm text-sky-blue" role="status">Mengunggah foto…</div>
                    @error('proof')
                        <p class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="button" wire:click="reviewPayment" wire:loading.attr="disabled" wire:target="reviewPayment">
                    <span wire:loading.remove wire:target="reviewPayment">Tinjau sebelum bayar</span>
                    <span wire:loading wire:target="reviewPayment">Memeriksa...</span>
                </x-ui.button>

                @if ($showPaymentReview)
                    <div class="rounded-md border-2 border-harvest-gold bg-warning-bg p-4" role="alert">
                        <h3 class="text-title font-bold text-deep-green">Konfirmasi pembayaran final</h3>
                        <p class="mt-1 text-body-sm text-text-primary">Pembayaran akan mengubah hold menjadi saldo keluar dan tidak dapat diulang. Pastikan penerima, nominal, dan bukti sudah benar.</p>
                        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <x-ui.button type="button" variant="secondary" wire:click="cancelPaymentReview">Ubah data</x-ui.button>
                            <x-ui.button type="button" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
                                <span wire:loading.remove wire:target="pay">Bayar dan catat bukti</span>
                                <span wire:loading wire:target="pay">Memproses...</span>
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </x-ui.panel>
    @endif
</section>
