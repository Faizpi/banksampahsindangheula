<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-overview-title">
        <p class="text-sm font-semibold text-forest-700">Administrasi sistem</p>
        <h2 id="technical-overview-title" class="mt-1 text-2xl font-bold text-deep-green">Kontrol teknis</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Pilih satu area kerja. Kontrol berisiko tidak lagi ditumpuk dalam satu halaman panjang.</p>
    </section>

    <nav class="backoffice-section-nav mt-6 overflow-x-auto" aria-label="Bagian kontrol teknis">
        <div class="flex min-w-max">
            <a href="{{ \App\Filament\Pages\TechnicalHealthPage::getUrl() }}"><x-filament::icon icon="heroicon-o-heart" aria-hidden="true" />Health</a>
            @if ($canManageSettings)
                <a href="{{ \App\Filament\Pages\TechnicalSettingsPage::getUrl() }}"><x-filament::icon icon="heroicon-o-cog-6-tooth" aria-hidden="true" />Pengaturan</a>
            @endif
            @if ($canManageMaintenance)
                <a href="{{ \App\Filament\Pages\TechnicalMaintenancePage::getUrl() }}"><x-filament::icon icon="heroicon-o-wrench-screwdriver" aria-hidden="true" />Pemeliharaan</a>
            @endif
            @if ($canViewBackups || $canRunBackup || $canRestoreBackup)
                <a href="{{ \App\Filament\Pages\TechnicalBackupsPage::getUrl() }}"><x-filament::icon icon="heroicon-o-archive-box" aria-hidden="true" />Cadangan</a>
            @endif
            @if ($canExecuteRetention)
                <a href="{{ \App\Filament\Pages\TechnicalAuditRetentionPage::getUrl() }}"><x-filament::icon icon="heroicon-o-clock" aria-hidden="true" />Retensi audit</a>
            @endif
            @if ($canExecuteMediaRetention)
                <a href="{{ \App\Filament\Pages\TechnicalMediaRetentionPage::getUrl() }}"><x-filament::icon icon="heroicon-o-photo" aria-hidden="true" />Retensi foto</a>
            @endif
        </div>
    </nav>

    <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600">Health bersifat baca-saja. Pengaturan, pemeliharaan, cadangan, dan retensi hanya muncul jika akun memiliki izin teknis terkait.</p>
</x-filament-panels::page>
