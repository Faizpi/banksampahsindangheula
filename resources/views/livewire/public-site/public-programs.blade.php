@section('title', 'Target dan Statistik Program')
@section('description', 'Target pengumpulan dan statistik agregat publik Bank Sampah Digital Desa Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="programs-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Program desa</p>
            <h1 id="programs-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Bergerak lewat angka agregat.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Target dan progres ditampilkan tanpa nama, nomor nasabah, alamat, saldo, atau riwayat individu.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('public.announcements') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Lihat pengumuman program
                    <x-public.icon name="megaphone" size="size-5" />
                </a>
                <a href="{{ route('public.mobile-schedule') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Lihat jadwal layanan
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-8.png') }}" alt="Maskot badak menanam bibit sebagai simbol dampak program" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas">
        <div class="public-container section-space">
            <x-public.section-header
                id="programs-title"
                eyebrow="Program berjalan"
                title="Target aktif"
                description="Target yang tampil telah melewati ambang publikasi dan hanya memuat ukuran agregat program."
            />

            @if ($targets->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Belum ada target publik"
                    description="Target ditampilkan setelah memenuhi ambang privasi publik."
                    action-label="Lihat pengumuman program"
                    action-href="{{ route('public.announcements') }}"
                />
            @else
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    @foreach ($targets as $target)
                        <article class="rounded-lg border border-border bg-surface p-5 md:p-6">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-md bg-success-bg text-forest-700" aria-hidden="true">
                                    <x-public.icon name="target" size="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-body-sm font-semibold text-forest-600">{{ $target['period'] }}</p>
                                    <h3 class="mt-1 text-h3 text-deep-green">{{ $target['name'] }}</h3>
                                </div>
                            </div>
                            <p class="mt-4 text-body-sm leading-6 text-text-secondary">{{ $target['purpose'] }}</p>
                            <p class="mt-3 text-body-sm leading-6 text-text-secondary"><span class="font-semibold text-text-primary">Cakupan:</span> {{ $target['scope'] }}</p>
                            <dl class="mt-6 grid gap-3 border-t border-border pt-4 text-body-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-text-secondary">Progres bersih</dt>
                                    <dd class="font-semibold tabular-nums text-deep-green">{{ \App\Support\WeightFormatter::format($target['progress_kg']) }} kg</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-text-secondary">Sasaran</dt>
                                    <dd class="font-semibold tabular-nums text-text-primary">{{ \App\Support\WeightFormatter::format($target['target_weight_kg']) }} kg</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif

            <section class="mt-12 border-t border-border pt-8" aria-labelledby="public-statistics-title">
                <x-public.section-header
                    id="public-statistics-title"
                    eyebrow="Statistik agregat"
                    title="Ringkasan desa"
                    description="Ringkasan ini hanya menampilkan metrik yang diizinkan untuk publikasi dan telah memenuhi ambang privasi."
                />
                <p class="mt-3 text-body-sm text-text-secondary">Periode 12 bulan: {{ \Carbon\CarbonImmutable::parse($statistics['period']['start'])->translatedFormat('d F Y') }} – {{ \Carbon\CarbonImmutable::parse($statistics['period']['end'])->subDay()->translatedFormat('d F Y') }}</p>
                @if ($rtFilteringEnabled)
                    <div class="mt-5 max-w-sm">
                        <label for="public-statistics-rt" class="block text-label font-semibold text-text-primary">Cakupan statistik</label>
                        <select id="public-statistics-rt" wire:model.live="rtId" class="mt-2 min-h-touch w-full rounded-md border border-border bg-surface px-3 text-body text-text-primary focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/30">
                            <option value="">Seluruh desa</option>
                            @foreach ($rts as $rt)
                                <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-body-sm text-text-secondary">{{ $statistics['rt_id'] === null ? 'Menampilkan agregat seluruh desa.' : 'Menampilkan agregat untuk RT terpilih.' }}</p>
                    </div>
                @endif

                @if ($statistics['suppressed'])
                    <div class="mt-6 flex gap-3 rounded-lg border border-border bg-surface p-5" role="status">
                        <x-public.icon name="circle-alert" size="size-6" class="mt-0.5 text-harvest-gold" />
                        <div>
                            <h3 class="text-title text-deep-green">Ringkasan belum ditampilkan</h3>
                            <p class="mt-1 text-body-sm text-text-secondary">Ringkasan belum ditampilkan karena belum memenuhi ambang privasi publik.</p>
                        </div>
                    </div>
                @else
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        @foreach ($statistics['metrics'] as $metric => $value)
                            <article class="rounded-lg border border-border bg-surface p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-body-sm text-text-secondary">{{ match ($metric) {
                                        'active_customers' => 'Nasabah aktif',
                                        'deposit_count' => 'Jumlah setoran',
                                        'total_weight_kg' => 'Total berat',
                                        'plastic_weight_kg' => 'Berat plastik',
                                        'dominant_waste_type' => 'Jenis sampah dominan',
                                        'target_progress_kg' => 'Progres target',
                                        'mobile_service_count' => 'Jumlah layanan keliling',
                                        default => 'Metrik publik',
                                    } }}</p>
                                    <x-public.icon name="bar-chart-3" size="size-5" class="text-forest-600" />
                                </div>
                                <p class="mt-3 text-amount tabular-nums text-deep-green">{{ in_array($metric, ['total_weight_kg', 'plastic_weight_kg', 'target_progress_kg'], true) && is_scalar($value) ? \App\Support\WeightFormatter::format((string) $value).' kg' : (is_scalar($value) ? $value : '—') }}</p>
                            </article>
                        @endforeach
                    </div>

                    @php
                        $charts = is_array($statistics['charts'] ?? null) ? $statistics['charts'] : [];
                        $weightTrend = is_array($charts['total_weight_kg'] ?? null) ? $charts['total_weight_kg'] : [];
                        $wasteComposition = is_array($charts['dominant_waste_type'] ?? null) ? $charts['dominant_waste_type'] : [];
                        $targetProgress = is_array($charts['target_progress_kg'] ?? null) ? $charts['target_progress_kg'] : [];
                        $chartPalette = ['var(--color-forest-600)', 'var(--color-sky-blue)', 'var(--color-harvest-gold)', 'var(--color-terracotta)'];
                    @endphp

                    @if ($weightTrend !== [] || $wasteComposition !== [] || $targetProgress !== [])
                        <div class="mt-8 grid gap-4 xl:grid-cols-2">
                            @if ($weightTrend !== [])
                                @php
                                    $trendValues = array_map(static fn (array $point): float => (float) ($point['total_weight_kg'] ?? 0), $weightTrend);
                                    $trendMaximum = max(max($trendValues), 1);
                                    $trendCount = count($weightTrend);
                                    $trendPoints = collect($trendValues)->map(static fn (float $value, int $index): string => number_format(8 + ($trendCount > 1 ? ($index / ($trendCount - 1)) * 84 : 42), 2, '.', '').','.number_format(88 - (($value / $trendMaximum) * 72), 2, '.', ''))->implode(' ');
                                @endphp
                                <article class="rounded-lg border border-border bg-surface p-5 md:p-6" aria-labelledby="weight-trend-title">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 id="weight-trend-title" class="text-h3 text-deep-green">Tren berat terkumpul</h3>
                                            <p class="mt-1 text-body-sm text-text-secondary">Berat setoran agregat per bulan.</p>
                                        </div>
                                        <span class="grid size-10 shrink-0 place-items-center rounded-md bg-info-bg text-sky-blue" aria-hidden="true"><x-public.icon name="bar-chart-3" size="size-5" /></span>
                                    </div>
                                    <svg class="mt-5 h-48 w-full" viewBox="0 0 100 100" role="img" aria-labelledby="weight-trend-title weight-trend-description" preserveAspectRatio="none">
                                        <desc id="weight-trend-description">Grafik garis berat setoran agregat menurut bulan dalam kilogram.</desc>
                                        <path d="M 8 16 H 92 M 8 52 H 92 M 8 88 H 92" fill="none" stroke="var(--color-border)" stroke-width="0.6" vector-effect="non-scaling-stroke" />
                                        <polyline points="{{ $trendPoints }}" fill="none" stroke="var(--color-forest-600)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                                        @foreach ($trendValues as $index => $value)
                                            @php($pointX = 8 + ($trendCount > 1 ? ($index / ($trendCount - 1)) * 84 : 42))
                                            @php($pointY = 88 - (($value / $trendMaximum) * 72))
                                            <circle cx="{{ $pointX }}" cy="{{ $pointY }}" r="1.8" fill="var(--color-surface)" stroke="var(--color-forest-600)" stroke-width="1.4" vector-effect="non-scaling-stroke" />
                                        @endforeach
                                    </svg>
                                    <div class="mt-4 overflow-x-auto">
                                        <table class="w-full min-w-max text-left text-body-sm">
                                            <caption class="public-live-region">Nilai tren berat terkumpul per bulan</caption>
                                            <thead class="border-b border-border text-text-secondary"><tr><th scope="col" class="pb-2 pr-4 font-semibold">Bulan</th><th scope="col" class="pb-2 text-right font-semibold">Berat</th></tr></thead>
                                            <tbody class="divide-y divide-border">
                                                @foreach ($weightTrend as $point)
                                                    <tr><th scope="row" class="py-2 pr-4 font-medium text-text-primary">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m', (string) $point['month'])->translatedFormat('M Y') }}</th><td class="py-2 text-right font-semibold tabular-nums text-deep-green">{{ \App\Support\WeightFormatter::format((string) $point['total_weight_kg']) }} kg</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </article>
                            @endif

                            @if ($wasteComposition !== [])
                                @php($compositionTotal = max(array_sum(array_map(static fn (array $segment): float => (float) ($segment['weight_kg'] ?? 0), $wasteComposition)), 1))
                                <article class="rounded-lg border border-border bg-surface p-5 md:p-6" aria-labelledby="waste-composition-title">
                                    <div class="flex items-start justify-between gap-4"><div><h3 id="waste-composition-title" class="text-h3 text-deep-green">Komposisi sampah</h3><p class="mt-1 text-body-sm text-text-secondary">Jenis sampah berdasarkan berat agregat.</p></div><span class="grid size-10 shrink-0 place-items-center rounded-md bg-success-bg text-forest-700" aria-hidden="true"><x-public.icon name="recycle" size="size-5" /></span></div>
                                    <div class="mt-5 grid items-center gap-5 sm:grid-cols-[9rem_1fr]">
                                        <svg class="mx-auto size-36 -rotate-90" viewBox="0 0 42 42" role="img" aria-labelledby="waste-composition-title waste-composition-description"><desc id="waste-composition-description">Diagram donat komposisi berat sampah menurut jenis.</desc><circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--color-disabled-bg)" stroke-width="7" />@php($offset = 25) @foreach ($wasteComposition as $index => $segment) @php($percent = ((float) $segment['weight_kg'] / $compositionTotal) * 100) <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $chartPalette[$index % count($chartPalette)] }}" stroke-width="7" stroke-dasharray="{{ $percent }} {{ 100 - $percent }}" stroke-dashoffset="{{ $offset }}" /> @php($offset -= $percent) @endforeach</svg>
                                        <ul class="space-y-3" aria-label="Legenda komposisi sampah">
                                            @foreach ($wasteComposition as $index => $segment)
                                                @php($percent = ((float) $segment['weight_kg'] / $compositionTotal) * 100)
                                                <li class="flex items-start justify-between gap-3 text-body-sm"><span class="flex min-w-0 items-center gap-2 text-text-primary"><span class="mt-1.5 size-2.5 shrink-0 rounded-sm" style="background-color: {{ $chartPalette[$index % count($chartPalette)] }}" aria-hidden="true"></span><span>{{ $segment['waste_type'] }}</span></span><span class="shrink-0 text-right font-semibold tabular-nums text-deep-green">{{ \App\Support\WeightFormatter::format((string) $segment['weight_kg']) }} kg<br><span class="font-normal text-text-secondary">{{ number_format($percent, 1, ',', '.') }}%</span></span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </article>
                            @endif

                            @if ($targetProgress !== [])
                                <article class="rounded-lg border border-border bg-surface p-5 md:p-6 xl:col-span-2" aria-labelledby="target-progress-title">
                                    <div class="flex items-start justify-between gap-4"><div><h3 id="target-progress-title" class="text-h3 text-deep-green">Progres target publik</h3><p class="mt-1 text-body-sm text-text-secondary">Perbandingan berat terkumpul dengan sasaran setiap target publik.</p></div><span class="grid size-10 shrink-0 place-items-center rounded-md bg-warning-bg text-harvest-gold" aria-hidden="true"><x-public.icon name="target" size="size-5" /></span></div>
                                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                                        @foreach ($targetProgress as $target)
                                            @php($targetWeight = max((float) ($target['target_weight_kg'] ?? 0), 1))
                                            @php($progressWeight = max((float) ($target['progress_kg'] ?? 0), 0))
                                            @php($progressPercent = min(($progressWeight / $targetWeight) * 100, 100))
                                            <div class="border-t border-border pt-4"><div class="flex items-start justify-between gap-4"><div><p class="text-caption font-semibold tracking-wide text-forest-600">{{ $target['target_number'] }}</p><h4 class="mt-1 text-title text-deep-green">{{ $target['name'] }}</h4></div><p class="shrink-0 text-right text-body-sm font-semibold tabular-nums text-deep-green">{{ \App\Support\WeightFormatter::format((string) $target['progress_kg']) }} kg<span class="block font-normal text-text-secondary">dari {{ \App\Support\WeightFormatter::format((string) $target['target_weight_kg']) }} kg</span></p></div><progress class="mt-3 h-3 w-full accent-forest-600" value="{{ $progressWeight }}" max="{{ $targetWeight }}">{{ number_format($progressPercent, 1, ',', '.') }}%</progress><p class="mt-1 text-caption text-text-secondary">{{ number_format($progressPercent, 1, ',', '.') }}% dari sasaran</p></div>
                                        @endforeach
                                    </div>
                                </article>
                            @endif
                        </div>
                    @else
                        <div class="mt-8 rounded-lg border border-border bg-surface p-5" role="status"><h3 class="text-title text-deep-green">Data visual belum tersedia</h3><p class="mt-1 text-body-sm text-text-secondary">Belum ada data visual yang dapat dipublikasikan untuk periode ini.</p></div>
                    @endif
                @endif
            </section>
        </div>
    </section>
</div>
