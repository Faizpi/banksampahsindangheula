<x-slot:title>Statistik internal</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section class="space-y-6" aria-labelledby="internal-statistics-title">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pengawasan berbasis data</p>
            <h1 id="internal-statistics-title" class="mt-1 text-h1 font-bold text-deep-green">Statistik internal</h1>
            <p class="mt-2 text-body text-text-secondary">Data mengikuti cakupan akun dan metrik dengan jumlah subjek di bawah ambang privasi akan disamarkan.</p>
        </div>
        <x-ui.mascot variant="5" class="hidden h-20 w-auto sm:block" />
    </div>

    <x-ui.panel title="Filter statistik" description="Periode akhir bersifat eksklusif. Pilihan RT hanya memuat wilayah yang boleh Anda lihat.">
        <form wire:submit="refreshStatistics" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input wire:model="start" name="start" label="Mulai" type="date" :error="$errors->first('start')" />
            <x-ui.input wire:model="end" name="end" label="Sampai" type="date" :error="$errors->first('end')" />
            <x-ui.select wire:model="rtId" name="rtId" label="RT (opsional)" :error="$errors->first('rtId')">
                <option value="">Semua RT dalam cakupan</option>
                @foreach ($rts as $rt)
                    <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                @endforeach
            </x-ui.select>
            <div class="self-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="refreshStatistics">Terapkan Filter</span>
                    <span wire:loading wire:target="refreshStatistics">Memuat...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    @if (($statistics['suppressed'] ?? true) === true)
        <x-ui.panel title="Statistik disamarkan" description="Jumlah subjek pada filter ini belum memenuhi ambang privasi. Detail transaksi tidak ditampilkan." state="warning" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['key' => 'active_customers', 'label' => 'Nasabah aktif'],
                ['key' => 'deposit_count', 'label' => 'Setoran'],
                ['key' => 'total_weight_kg', 'label' => 'Total berat', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'plastic_weight_kg', 'label' => 'Berat plastik', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'target_progress_kg', 'label' => 'Progres target', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'mobile_service_count', 'label' => 'Layanan keliling'],
                ['key' => 'subject_count', 'label' => 'Subjek terukur'],
                ['key' => 'dominant_waste_type', 'label' => 'Jenis dominan'],
            ] as $metric)
                <article class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                    <p class="text-caption font-semibold text-text-secondary">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-h2 font-bold tabular-nums text-deep-green">{{ ($metric['format'] ?? null) === 'weight' ? \App\Support\WeightFormatter::format($statistics[$metric['key']] ?? null) : ($statistics[$metric['key']] ?? '—') }}{{ $metric['suffix'] ?? '' }}</p>
                </article>
            @endforeach
        </div>

        @php
            $charts = is_array($statistics['charts'] ?? null) ? $statistics['charts'] : [];
            $weightTrend = is_array($charts['total_weight_kg'] ?? null) ? $charts['total_weight_kg'] : [];
            $composition = is_array($charts['dominant_waste_type'] ?? null) ? $charts['dominant_waste_type'] : [];
            $targetProgress = is_array($charts['target_progress_kg'] ?? null) ? $charts['target_progress_kg'] : [];
            $trendMaximum = max(collect($weightTrend)->pluck('total_weight_kg')->map(static fn (mixed $weight): float => (float) $weight)->all() ?: [0]);
            $compositionTotal = collect($composition)->sum(static fn (array $segment): float => (float) ($segment['weight_kg'] ?? 0));
        @endphp

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel title="Tren berat setoran" description="Total berat setoran final per bulan dalam periode yang dipilih.">
                @if ($weightTrend === [])
                    <p class="text-body text-text-secondary">Belum ada data tren untuk filter ini.</p>
                @else
                    <div class="overflow-x-auto">
                        <svg class="min-w-[32rem] text-forest-600" viewBox="0 0 600 220" role="img" aria-labelledby="weight-trend-title weight-trend-description">
                            <title id="weight-trend-title">Tren total berat setoran</title>
                            <desc id="weight-trend-description">{{ collect($weightTrend)->map(static fn (array $point): string => $point['month'].': '.\App\Support\WeightFormatter::format($point['total_weight_kg']).' kg')->implode(', ') }}</desc>
                            <path d="M 40 180 H 580" class="stroke-border" fill="none" stroke-width="2" />
                            @foreach ($weightTrend as $index => $point)
                                @php
                                    $count = count($weightTrend);
                                    $x = $count === 1 ? 310 : 40 + ($index * 540 / ($count - 1));
                                    $y = $trendMaximum > 0 ? 180 - (((float) $point['total_weight_kg'] / $trendMaximum) * 140) : 180;
                                @endphp
                                @if ($index > 0)
                                    @php
                                        $previous = $weightTrend[$index - 1];
                                        $previousX = $count === 1 ? 310 : 40 + (($index - 1) * 540 / ($count - 1));
                                        $previousY = $trendMaximum > 0 ? 180 - (((float) $previous['total_weight_kg'] / $trendMaximum) * 140) : 180;
                                    @endphp
                                    <path d="M {{ $previousX }} {{ $previousY }} L {{ $x }} {{ $y }}" class="stroke-current" fill="none" stroke-width="4" />
                                @endif
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="6" class="fill-surface stroke-current" stroke-width="4" />
                                <text x="{{ $x }}" y="205" text-anchor="middle" class="fill-text-secondary text-caption">{{ $point['month'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                    <table class="sr-only">
                        <caption>Nilai tren berat setoran per bulan</caption>
                        <thead><tr><th scope="col">Bulan</th><th scope="col">Total berat</th></tr></thead>
                        <tbody>@foreach ($weightTrend as $point)<tr><th scope="row">{{ $point['month'] }}</th><td>{{ \App\Support\WeightFormatter::format($point['total_weight_kg']) }} kg</td></tr>@endforeach</tbody>
                    </table>
                @endif
            </x-ui.panel>

            <x-ui.panel title="Komposisi jenis sampah" description="Proporsi berat setiap jenis sampah pada periode yang dipilih.">
                @if ($composition === [] || $compositionTotal <= 0)
                    <p class="text-body text-text-secondary">Belum ada komposisi sampah untuk filter ini.</p>
                @else
                    @php
                        $donutCircumference = 314.159;
                        $donutOffset = 0.0;
                        $compositionPalette = [
                            ['stroke' => 'text-forest-600', 'swatch' => 'bg-forest-600'],
                            ['stroke' => 'text-sun-500', 'swatch' => 'bg-sun-500'],
                            ['stroke' => 'text-sky-600', 'swatch' => 'bg-sky-600'],
                            ['stroke' => 'text-terracotta-500', 'swatch' => 'bg-terracotta-500'],
                        ];
                    @endphp
                    <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center">
                        <svg class="mx-auto size-40" viewBox="0 0 128 128" role="img" aria-labelledby="composition-donut-title composition-donut-description">
                            <title id="composition-donut-title">Komposisi jenis sampah</title>
                            <desc id="composition-donut-description">{{ collect($composition)->map(static fn (array $segment): string => $segment['waste_type'].': '.\App\Support\WeightFormatter::format($segment['weight_kg']).' kg')->implode(', ') }}</desc>
                            <circle cx="64" cy="64" r="50" class="fill-none stroke-border" stroke-width="20" />
                            @foreach ($composition as $index => $segment)
                                @php
                                    $percentage = ((float) $segment['weight_kg'] / $compositionTotal) * 100;
                                    $segmentLength = $donutCircumference * ($percentage / 100);
                                    $gap = count($composition) > 1 ? min(2, $segmentLength) : 0;
                                    $visibleLength = max($segmentLength - $gap, 0);
                                    $palette = $compositionPalette[$index % count($compositionPalette)];
                                    $dashOffset = -$donutOffset;
                                    $donutOffset += $segmentLength;
                                @endphp
                                <circle cx="64" cy="64" r="50" class="fill-none {{ $palette['stroke'] }}" stroke="currentColor" stroke-width="20" stroke-dasharray="{{ $visibleLength }} {{ $donutCircumference - $visibleLength }}" stroke-dashoffset="{{ $dashOffset }}" transform="rotate(-90 64 64)" />
                            @endforeach
                            <circle cx="64" cy="64" r="36" class="fill-surface" />
                            <text x="64" y="60" text-anchor="middle" class="fill-deep-green text-caption font-semibold">{{ \App\Support\WeightFormatter::format($compositionTotal) }}</text>
                            <text x="64" y="76" text-anchor="middle" class="fill-text-secondary text-caption">kg</text>
                        </svg>
                        <ul class="space-y-3" aria-label="Legenda komposisi jenis sampah">
                            @foreach ($composition as $index => $segment)
                                @php
                                    $percentage = ((float) $segment['weight_kg'] / $compositionTotal) * 100;
                                    $palette = $compositionPalette[$index % count($compositionPalette)];
                                @endphp
                                <li class="flex items-start justify-between gap-4 border-b border-border pb-3 last:border-0 last:pb-0">
                                    <span class="text-body font-semibold text-deep-green"><span class="mr-2 inline-block size-3 rounded-sm {{ $palette['swatch'] }}"></span>{{ $segment['waste_type'] }}</span>
                                    <span class="text-caption tabular-nums text-text-secondary">{{ \App\Support\WeightFormatter::format($segment['weight_kg']) }} kg · {{ number_format($percentage, 1, ',', '.') }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <table class="sr-only">
                        <caption>Nilai komposisi jenis sampah</caption>
                        <thead><tr><th scope="col">Jenis sampah</th><th scope="col">Berat</th><th scope="col">Proporsi</th></tr></thead>
                        <tbody>@foreach ($composition as $segment)<tr><th scope="row">{{ $segment['waste_type'] }}</th><td>{{ \App\Support\WeightFormatter::format($segment['weight_kg']) }} kg</td><td>{{ number_format(((float) $segment['weight_kg'] / $compositionTotal) * 100, 1, ',', '.') }}%</td></tr>@endforeach</tbody>
                    </table>
                @endif
            </x-ui.panel>
        </div>

        <x-ui.panel title="Progres per target" description="Pencapaian berat terhadap setiap target aktif atau selesai dalam periode yang dipilih.">
            @if ($targetProgress === [])
                <p class="text-body text-text-secondary">Belum ada target yang relevan untuk filter ini.</p>
            @else
                <div class="space-y-5">
                    @foreach ($targetProgress as $target)
                        @php
                            $targetWeight = (float) ($target['target_weight_kg'] ?? 0);
                            $progressWeight = (float) ($target['progress_kg'] ?? 0);
                            $percentage = $targetWeight > 0 ? min(($progressWeight / $targetWeight) * 100, 100) : 0;
                        @endphp
                        <article aria-labelledby="target-{{ $loop->index }}" class="space-y-2">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                <div>
                                    <p id="target-{{ $loop->index }}" class="text-body font-semibold text-deep-green">{{ $target['name'] }}</p>
                                    <p class="text-caption text-text-secondary">{{ $target['target_number'] }}</p>
                                </div>
                                <p class="text-caption tabular-nums text-text-secondary">{{ \App\Support\WeightFormatter::format($target['progress_kg']) }} dari {{ \App\Support\WeightFormatter::format($target['target_weight_kg']) }} kg ({{ number_format($percentage, 1, ',', '.') }}%)</p>
                            </div>
                            <div class="h-3 overflow-hidden rounded-sm bg-border" role="progressbar" aria-labelledby="target-{{ $loop->index }}" aria-valuemin="0" aria-valuemax="{{ $targetWeight }}" aria-valuenow="{{ min($progressWeight, $targetWeight) }}" aria-valuetext="{{ \App\Support\WeightFormatter::format($target['progress_kg']) }} dari {{ \App\Support\WeightFormatter::format($target['target_weight_kg']) }} kg">
                                <div class="h-full rounded-sm bg-forest-600" style="width: {{ $percentage }}%"></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-ui.panel>
    @endif
</section>
