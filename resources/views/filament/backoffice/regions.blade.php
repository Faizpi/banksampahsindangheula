<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="regions-title">
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="regions-title" class="mt-1 text-2xl font-bold text-deep-green">Wilayah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola struktur area, dusun, RW, dan RT dari satu pintu agar penugasan dan cakupan layanan tetap konsisten.</p>
    </section>

    <nav class="backoffice-section-nav mt-6 overflow-x-auto" aria-label="Bagian wilayah">
        <div class="flex min-w-max">
            @if ($canViewAreas)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-map" aria-hidden="true" />Area pelayanan</a>@endif
            @if ($canViewDusuns)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-home-modern" aria-hidden="true" />Dusun</a>@endif
            @if ($canViewRws)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rws\RwResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-building-office-2" aria-hidden="true" />RW</a>@endif
            @if ($canViewRts)<a href="{{ \App\Filament\Resources\CustomersRegions\Models\Rts\RtResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-map-pin" aria-hidden="true" />RT</a>@endif
        </div>
    </nav>
</x-filament-panels::page>
