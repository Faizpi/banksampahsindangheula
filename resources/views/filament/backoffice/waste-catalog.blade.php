<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="waste-catalog-title">
        <p class="text-sm font-semibold text-forest-700">Data master</p>
        <h2 id="waste-catalog-title" class="mt-1 text-2xl font-bold text-deep-green">Katalog Sampah</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Kelola kategori, jenis, kondisi, satuan, dan harga dari satu area yang mudah ditemukan.</p>
    </section>

    <nav class="backoffice-section-nav mt-6 overflow-x-auto" aria-label="Bagian katalog sampah">
        <div class="flex min-w-max">
            @if ($canViewCategories)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-squares-2x2" aria-hidden="true" />Kategori</a>@endif
            @if ($canViewTypes)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-tag" aria-hidden="true" />Jenis</a>@endif
            @if ($canViewConditions)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-clipboard-document-check" aria-hidden="true" />Kondisi</a>@endif
            @if ($canViewPrices)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-banknotes" aria-hidden="true" />Harga</a>@endif
            @if ($canViewUnits)<a href="{{ \App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource::getUrl('index') }}"><x-filament::icon icon="heroicon-o-scale" aria-hidden="true" />Satuan</a>@endif
        </div>
    </nav>
</x-filament-panels::page>
