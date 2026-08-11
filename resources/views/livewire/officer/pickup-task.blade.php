<x-slot:title>Tugas penjemputan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="pickup-task-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Tugas {{ $pickup->request_number }}</p>
            <h1 id="pickup-task-title" class="mt-2 text-h1 font-bold text-deep-green">Penjemputan Lapangan</h1>
            <p class="mt-3 text-body text-text-secondary">
                <svg viewBox="0 0 24 24" class="mr-1 inline size-4 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $pickup->address }}
            </p>
        </div>
        <x-ui.mascot variant="8" bubble="Menuju lokasi!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if ($errors->any())
        <div id="pickup-task-errors" tabindex="-1" role="alert" class="rounded-lg border border-terracotta bg-danger-bg p-4">
            <p class="text-title font-bold text-deep-green">Periksa data tugas</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-body-sm text-text-primary">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Status Panel --}}
    <x-ui.panel title="Status tugas" description="Urutan status harus diikuti. Estimasi tidak digunakan untuk saldo.">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-info-bg bg-info-bg px-4 py-1.5 text-label font-bold text-sky-blue">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ \App\Support\StatusLabel::for($pickup->status) }}
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            @if ($pickup->status->value === 'dijadwalkan')
                <button type="button" wire:click="begin" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-sky-blue px-5 text-label font-bold text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l19-9-9 19-2-8-8-2Z"/></svg>
                    Menuju Lokasi
                </button>
            @endif
            @if ($pickup->status->value === 'menuju_lokasi')
                <button type="button" wire:click="markPickedUp" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    Tandai Dijemput
                </button>
            @endif
            @if (in_array($pickup->status->value, ['dijadwalkan', 'menuju_lokasi', 'dijemput'], true))
                <button type="button" wire:click="openFailureReport"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    Laporkan Kendala
                </button>
            @endif
        </div>
    </x-ui.panel>

    {{-- Actual Weighing --}}
    @if ($canComplete)
        <x-ui.panel title="Timbang aktual" description="Berat aktual ini menjadi sumber setoran dan saldo." state="success">
            <div class="grid gap-4">
                @foreach ($actualItems as $index => $item)
                    <div wire:key="actual-item-{{ $index }}" class="grid gap-3 rounded-xl border border-border bg-warm-canvas p-4 md:grid-cols-3">
                        <x-ui.select wire:model="actualItems.{{ $index }}.waste_type_id"
                            label="Jenis sampah" name="actualItems.{{ $index }}.waste_type_id"
                            placeholder="Pilih jenis sampah"
                            :error="$errors->first('actualItems.'.$index.'.waste_type_id')"
                            :options="$types->pluck('name', 'id')->all()" />
                        <x-ui.select wire:model="actualItems.{{ $index }}.condition_id"
                            label="Kondisi" name="actualItems.{{ $index }}.condition_id"
                            placeholder="Pilih kondisi"
                            :error="$errors->first('actualItems.'.$index.'.condition_id')"
                            :options="$conditions->pluck('name', 'id')->all()" />
                        <x-ui.input wire:model="actualItems.{{ $index }}.weight_kg"
                            label="Berat aktual (kg)" name="actualItems.{{ $index }}.weight_kg"
                            inputmode="decimal" :error="$errors->first('actualItems.'.$index.'.weight_kg')" />
                    </div>
                @endforeach

                <div class="rounded-xl border border-border bg-warm-canvas p-4" data-photo-picker data-photo-picker-max="1" data-photo-picker-limit="1048576">
                    <div>
                        <h3 class="text-label font-bold text-deep-green">Bukti foto penjemputan</h3>
                        <p class="mt-1 text-body-sm text-text-secondary">Ambil 1 foto di lokasi melalui kamera atau pilih dari galeri. Foto akan dikompres menjadi JPEG maksimal 1 MB. Foto pengajuan warga tetap dipakai bila bukti baru tidak ditambahkan.</p>
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
                    <input id="pickup-evidence" wire:model="evidence" type="file" accept="image/jpeg,image/png" data-photo-picker-input class="mt-3 block min-h-touch w-full rounded-md border-2 border-dashed border-border bg-surface p-4 text-body text-text-secondary transition hover:border-forest-600 focus:outline-none focus:ring-2 focus:ring-focus">
                    <p data-photo-picker-status class="mt-2 text-body-sm text-text-secondary" aria-live="polite">Belum ada foto baru dipilih.</p>
                    <div data-photo-picker-preview class="mt-2 grid gap-2" aria-live="polite"></div>
                    @error('evidence')<p class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-md border border-border bg-surface p-4" aria-live="polite">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                        <div>
                            <h3 class="text-label font-bold text-deep-green">Review nilai setoran</h3>
                            <p class="text-body-sm text-text-secondary">Harga aktif dan subtotal akan dihitung ulang server saat finalisasi.</p>
                        </div>
                        @if ($pricePreview['complete'])
                            <strong class="amount-tabular text-title text-forest-700">Rp {{ number_format($pricePreview['total'], 0, ',', '.') }}</strong>
                        @else
                            <span class="text-body-sm font-semibold text-harvest-gold">Lengkapi item untuk melihat nilai</span>
                        @endif
                    </div>
                    @if ($pricePreview['lines'] !== [])
                        <dl class="mt-3 divide-y divide-border border-t border-border text-body-sm">
                            @foreach ($pricePreview['lines'] as $line)
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="min-w-0"><span class="block font-semibold text-deep-green">{{ $line['name'] }}</span><span class="text-text-secondary">{{ $line['condition'] }} · {{ $line['weight'] }} kg</span></dt>
                                    <dd class="shrink-0 amount-tabular font-semibold text-deep-green">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button"
                        wire:click="$set('actualItems', [...$actualItems, ['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => '']])"
                        class="inline-flex min-h-touch items-center gap-2 justify-self-start rounded-xl border-2 border-forest-600 px-4 text-label font-bold text-forest-700 transition hover:bg-success-bg">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Tambah Detail Timbang
                    </button>
                    <x-ui.button type="button" wire:click="complete" wire:loading.attr="disabled">
                        <span wire:loading.remove>Finalkan Setoran Aktual</span>
                        <span wire:loading>Memproses...</span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    {{-- Status History --}}
    <x-ui.panel title="Riwayat status" description="Perubahan status tercatat untuk penelusuran.">
        <x-ui.timeline :steps="$pickup->statusHistory->map(fn ($history): array => [
            'title'  => \App\Support\StatusLabel::for($history->new_status),
            'note'   => $history->reason ?? '',
            'time'   => $history->occurred_at->translatedFormat('d M Y, H:i'),
            'status' => match($history->new_status) {
                'selesai'    => 'success',
                'ditolak', 'dibatalkan' => 'error',
                default      => 'in_progress',
            },
        ])->all()" />
    </x-ui.panel>

    @if ($failureDialogOpen)
        <div class="fixed inset-0 z-overlay flex items-end justify-center bg-overlay p-4 sm:items-center" role="presentation">
            <div class="w-full max-w-form rounded-lg border border-border bg-surface p-5 shadow-dialog sm:p-6" role="dialog" aria-modal="true" aria-labelledby="failure-dialog-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="failure-dialog-title" class="text-h2 font-bold text-deep-green">Laporkan kendala pickup</h2>
                        <p class="mt-1 text-body-sm text-text-secondary">Pengajuan akan dibatalkan dan alasan ini tercatat pada riwayat tugas.</p>
                    </div>
                    <button type="button" wire:click="closeFailureReport" aria-label="Tutup dialog" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md text-text-secondary hover:bg-warm-canvas">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <form wire:submit="reportFailure" class="mt-5 grid gap-4">
                    <x-ui.textarea name="failureReason" label="Jelaskan kendala" wire:model="failureReason" rows="4" hint="Minimal 10 karakter. Contoh: alamat tidak dapat diakses setelah dua kali percobaan." :error="$errors->first('failureReason')" />
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" wire:click="closeFailureReport">Kembali</x-ui.button>
                        <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="reportFailure">
                            <span wire:loading.remove wire:target="reportFailure">Catat dan hentikan pickup</span>
                            <span wire:loading wire:target="reportFailure">Menyimpan...</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
