<x-filament-panels::page>
    <section class="rounded-xl border border-warning-200 bg-warning-50 p-5 shadow-sm sm:p-6" aria-labelledby="reconciliation-title">
        <p class="text-sm font-semibold text-warning-900">Pengawasan keuangan</p>
        <h2 id="reconciliation-title" class="mt-1 text-2xl font-bold text-gray-950">Koreksi dan rekonsiliasi</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-700">Area ini untuk pemeriksaan berisiko tinggi. Pekerjaan rutin tetap dilakukan dari Operasional; setiap koreksi, reversal, dan penyesuaian harus punya alasan serta jejak audit.</p>
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
