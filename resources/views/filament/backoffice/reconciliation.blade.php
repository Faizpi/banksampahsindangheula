<x-filament-panels::page>
    <section class="rounded-xl border border-warning-200 bg-warning-50 p-5 shadow-sm sm:p-6" aria-labelledby="reconciliation-title">
        <p class="text-sm font-semibold text-warning-900">Pengawasan keuangan</p>
        <h2 id="reconciliation-title" class="mt-1 text-2xl font-bold text-gray-950">Koreksi dan rekonsiliasi</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-700">Area ini untuk pemeriksaan berisiko tinggi. Pekerjaan rutin tetap dilakukan dari Operasional; setiap koreksi, reversal, dan penyesuaian harus punya alasan serta jejak audit.</p>
    </section>

    @if (session('reconciliation-success'))
        <div class="mt-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-800" role="status">{{ session('reconciliation-success') }}</div>
    @endif

    @if ($canCreate)
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="snapshot-title">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="snapshot-title" class="text-lg font-bold text-gray-950">Buat snapshot harian</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-600">Sistem membandingkan setoran, pengeluaran saldo, dana ditahan, dan hitungan kas. Snapshot baru selalu menjadi versi berikutnya agar jejak sebelumnya tetap ada.</p>
                </div>
            </div>
            <form wire:submit="createSnapshot" class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="block text-sm font-semibold text-gray-800">Tanggal bisnis
                    <input wire:model="businessDate" type="date" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-600 focus:ring-primary-600">
                    @error('businessDate') <span class="mt-1 block text-xs text-danger-700">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-semibold text-gray-800">Kas pencairan (Rp)
                    <input wire:model="cashTotal" inputmode="numeric" type="text" placeholder="Isi bila sudah dihitung" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-600 focus:ring-primary-600">
                    @error('cashTotal') <span class="mt-1 block text-xs text-danger-700">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-semibold text-gray-800">Catatan awal
                    <input wire:model="notes" type="text" maxlength="2000" placeholder="Opsional" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-600 focus:ring-primary-600">
                    @error('notes') <span class="mt-1 block text-xs text-danger-700">{{ $message }}</span> @enderror
                </label>
                <div class="md:col-span-3"><button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60" wire:loading.attr="disabled">Buat snapshot</button></div>
            </form>
        </section>
    @endif

    <section class="mt-6 space-y-4" aria-labelledby="history-title">
        <div><h2 id="history-title" class="text-lg font-bold text-gray-950">Riwayat rekonsiliasi</h2><p class="mt-1 text-sm text-gray-600">Rekonsiliasi hanya dapat diajukan dan disetujui ketika seluruh pembanding sesuai.</p></div>
        @forelse ($reconciliations as $reconciliation)
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="font-bold text-gray-950">{{ $reconciliation->business_date->format('d M Y') }} · Versi {{ $reconciliation->version }}</h3><p class="mt-1 text-sm text-gray-600">Dibuat {{ $reconciliation->creator?->name ?? '—' }} · saldo awal Rp {{ number_format($reconciliation->opening_total, 0, ',', '.') }} · saldo akhir Rp {{ number_format($reconciliation->closing_total, 0, ',', '.') }}</p></div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $reconciliation->status === 'disetujui' ? 'bg-success-50 text-success-800' : ($reconciliation->status === 'ditolak' ? 'bg-danger-50 text-danger-800' : ($reconciliation->status === 'diajukan' ? 'bg-primary-50 text-primary-800' : 'bg-warning-50 text-warning-800')) }}">{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span>
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-2 py-2">Pembanding</th><th class="px-2 py-2 text-right">Harapan</th><th class="px-2 py-2 text-right">Aktual</th><th class="px-2 py-2 text-right">Selisih</th><th class="px-2 py-2">Status</th></tr></thead><tbody class="divide-y divide-gray-100">
                    @foreach ($reconciliation->items as $item)
                        <tr><td class="px-2 py-3 font-medium text-gray-800">{{ str_replace('_', ' ', $item->item_type) }}<span class="mt-0.5 block text-xs font-normal text-gray-500">{{ $item->note }}</span></td><td class="px-2 py-3 text-right tabular-nums">Rp {{ number_format($item->expected_total, 0, ',', '.') }}</td><td class="px-2 py-3 text-right tabular-nums">Rp {{ number_format($item->actual_total, 0, ',', '.') }}</td><td class="px-2 py-3 text-right tabular-nums {{ $item->difference === 0 ? 'text-success-700' : 'text-danger-700' }}">Rp {{ number_format($item->difference, 0, ',', '.') }}</td><td class="px-2 py-3 text-xs font-semibold">{{ ucfirst($item->status) }}</td></tr>
                    @endforeach
                </tbody></table></div>
                @if ($reconciliation->notes)<p class="mt-3 text-sm text-gray-600 whitespace-pre-line">{{ $reconciliation->notes }}</p>@endif
                @if ($reconciliation->status === 'draf' && $canCreate)
                    <div class="mt-4 flex flex-wrap items-end gap-3"><label class="block text-sm font-semibold text-gray-800">Kas pencairan (Rp)<input wire:model="cashTotal" inputmode="numeric" type="text" class="mt-1 block w-56 rounded-lg border-gray-300 text-sm shadow-sm"></label><button wire:click="saveCashCount({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg border border-primary-600 px-4 text-sm font-semibold text-primary-700 hover:bg-primary-50">Simpan kas</button><button wire:click="submitSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700">Ajukan</button></div>
                @endif
                @if ($reconciliation->status === 'diajukan' && $canApprove)
                    <div class="mt-4 flex flex-wrap items-end gap-3"><label class="block min-w-72 flex-1 text-sm font-semibold text-gray-800">Catatan keputusan<input wire:model="decisionReason" type="text" maxlength="1000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm"></label><button wire:click="approveSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-success-700 px-4 text-sm font-semibold text-white hover:bg-success-800">Setujui</button><button wire:click="rejectSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-danger-700 px-4 text-sm font-semibold text-white hover:bg-danger-800">Tolak</button></div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600">Belum ada snapshot. Buat rekonsiliasi untuk menutup pemeriksaan saldo harian.</div>
        @endforelse
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Bagian rekonsiliasi">
        <div class="flex min-w-max gap-6">
            @if ($canReviewDeposits)
                <a href="{{ \App\Filament\Resources\Deposits\Models\Deposits\DepositResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Koreksi setoran</a>
            @endif
            @if ($canViewLedger)
                <a href="{{ \App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Mutasi saldo</a>
            @endif
            @if ($canViewHolds)
                <a href="{{ \App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Dana ditahan</a>
            @endif
            @if ($canViewAudit)
                <a href="{{ \App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Audit log</a>
            @endif
        </div>
    </nav>
</x-filament-panels::page>
