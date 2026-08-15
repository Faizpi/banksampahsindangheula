<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-overview-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
        <p class="text-sm font-semibold text-forest-700">Administrasi sistem</p>
        <h2 id="technical-overview-title" class="mt-1 text-2xl font-bold text-deep-green">Kontrol teknis</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Pilih satu area kerja. Kontrol berisiko tidak lagi ditumpuk dalam satu halaman panjang.</p>
            </div>
            <img src="{{ asset('images/landing/mascot-10.png') }}" alt="Maskot badak memeriksa kesehatan dan keamanan sistem" class="h-24 w-24 self-end object-contain sm:h-28 sm:w-28 sm:self-auto">
        </div>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-border" aria-label="Bagian kontrol teknis">
        <div class="flex min-w-max gap-2 sm:gap-4">
            <a href="{{ \App\Filament\Pages\TechnicalHealthPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-heart" class="size-5 shrink-0" aria-hidden="true" /><span>Health</span></a>
            @if ($canManageSettings)
                <a href="{{ \App\Filament\Pages\TechnicalSettingsPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-cog-6-tooth" class="size-5 shrink-0" aria-hidden="true" /><span>Pengaturan</span></a>
            @endif
            @if ($canManageMaintenance)
                <a href="{{ \App\Filament\Pages\TechnicalMaintenancePage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-wrench-screwdriver" class="size-5 shrink-0" aria-hidden="true" /><span>Pemeliharaan</span></a>
            @endif
            @if ($canViewBackups || $canRunBackup || $canRestoreBackup)
                <a href="{{ \App\Filament\Pages\TechnicalBackupsPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-archive-box" class="size-5 shrink-0" aria-hidden="true" /><span>Cadangan</span></a>
            @endif
            @if ($canExecuteRetention)
                <a href="{{ \App\Filament\Pages\TechnicalAuditRetentionPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-clock" class="size-5 shrink-0" aria-hidden="true" /><span>Retensi audit</span></a>
            @endif
            @if ($canExecuteMediaRetention)
                <a href="{{ \App\Filament\Pages\TechnicalMediaRetentionPage::getUrl() }}" class="inline-flex min-h-12 items-center gap-2 border-b-2 border-transparent px-3 text-sm font-semibold text-text-secondary transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"><x-filament::icon icon="heroicon-o-photo" class="size-5 shrink-0" aria-hidden="true" /><span>Retensi foto</span></a>
            @endif
        </div>
    </nav>

    <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600">Health bersifat baca-saja. Pengaturan, pemeliharaan, cadangan, dan retensi hanya muncul jika akun memiliki izin teknis terkait.</p>
</x-filament-panels::page>
