<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StatisticsService
{
    /** @var list<string> */
    private const METRICS = ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'dominant_waste_type', 'target_progress_kg', 'mobile_service_count'];

    /** @var list<string> */
    private const DIMENSIONS = ['period', 'rt_id'];

    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
        private TargetProgressService $targetProgress,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @return array<string, mixed> */
    public function internal(User $actor, string $start, string $end, ?int $rtId = null): array
    {
        $this->authorize($actor, 'statistics.internal.view');
        if ($rtId !== null && ! $this->canViewRegion($actor, $rtId)) {
            throw new AuthorizationException('Wilayah statistik berada di luar scope Anda.');
        }
        $aggregate = $this->aggregate($start, $end, $rtId);
        $threshold = (int) config('app.statistics_privacy_threshold', 5);
        if ($aggregate['subject_count'] < $threshold) {
            $aggregate['suppressed'] = true;
            $aggregate['subject_count'] = null;
            $aggregate['deposit_count'] = null;
            $aggregate['total_weight_kg'] = null;
            $aggregate['plastic_weight_kg'] = null;
            $aggregate['dominant_waste_type'] = null;
            $aggregate['active_customers'] = null;
            $aggregate['target_progress_kg'] = null;
            $aggregate['mobile_service_count'] = null;
            $aggregate['charts'] = [];

            return $aggregate;
        }

        $aggregate['charts'] = $this->charts($start, $end, $rtId);

        return $aggregate;
    }

    /** @return array<string, mixed> */
    public function public(string $start, string $end, ?int $rtId = null): array
    {
        $period = ['start' => $start, 'end' => $end];
        $publication = StatisticPublication::query()->where('publication_key', 'public-dashboard')->where('is_active', true)->first();
        if ($publication === null) {
            return ['suppressed' => true, 'metrics' => [], 'charts' => [], 'period' => $period, 'rt_id' => null];
        }
        $rawDimensions = $publication->getAttribute('dimensions');
        $configuredDimensions = is_array($rawDimensions) ? array_values(array_filter($rawDimensions, static fn (mixed $dimension): bool => is_string($dimension))) : [];
        $aggregateRtId = in_array('rt_id', $configuredDimensions, true) ? $rtId : null;
        $aggregate = $this->aggregate($start, $end, $aggregateRtId, true);
        if ($aggregate['subject_count'] < $publication->privacy_threshold) {
            return ['suppressed' => true, 'metrics' => [], 'charts' => [], 'period' => $period, 'rt_id' => $aggregateRtId];
        }
        $rawMetrics = $publication->getAttribute('metrics');
        $configuredMetrics = is_array($rawMetrics) ? array_values(array_filter($rawMetrics, static fn (mixed $metric): bool => is_string($metric))) : [];
        $allowedMetrics = array_values(array_intersect($configuredMetrics, self::METRICS));
        $charts = $this->charts($start, $end, $aggregateRtId, $publication->privacy_threshold, true);
        $result = [];
        foreach ($allowedMetrics as $metric) {
            $result[$metric] = $metric === 'target_progress_kg'
                ? number_format((float) collect($charts['target_progress_kg'])->sum('progress_kg'), 3, '.', '')
                : $aggregate[$metric] ?? null;
        }
        $charts = array_intersect_key($charts, array_flip(array_intersect($allowedMetrics, ['total_weight_kg', 'dominant_waste_type', 'target_progress_kg'])));

        return [
            'suppressed' => false,
            'metrics' => $result,
            'charts' => $charts,
            'period' => $period,
            'rt_id' => $aggregateRtId,
        ];
    }

    /**
     * @param  list<string>  $metrics
     * @param  list<string>  $dimensions
     */
    public function configurePublic(User $actor, array $metrics, array $dimensions, int $threshold, bool $active): StatisticPublication
    {
        if (! $this->permissions->allows($actor, 'statistics.public.manage')) {
            throw new AuthorizationException('Anda tidak memiliki akses publikasi statistik.');
        }
        if (array_diff($metrics, self::METRICS) !== [] || array_diff($dimensions, self::DIMENSIONS) !== [] || $threshold < 2 || $threshold > 1000) {
            throw ValidationException::withMessages(['publication' => 'Metrik, dimensi, atau ambang statistik tidak diizinkan.']);
        }

        return DB::transaction(function () use ($actor, $metrics, $dimensions, $threshold, $active): StatisticPublication {
            $publication = StatisticPublication::query()->firstOrNew(['publication_key' => 'public-dashboard']);
            $old = $publication->exists ? ['metrics' => $publication->metrics, 'dimensions' => $publication->dimensions, 'privacy_threshold' => $publication->privacy_threshold, 'is_active' => $publication->is_active] : [];
            $publication->forceFill(['metrics' => array_values(array_unique($metrics)), 'dimensions' => array_values(array_unique($dimensions)), 'privacy_threshold' => $threshold, 'is_active' => $active, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->auditLogger->record($actor, 'statistics.publication.configured', $publication, $old, ['metrics' => $publication->metrics, 'dimensions' => $publication->dimensions, 'privacy_threshold' => $publication->privacy_threshold, 'is_active' => $publication->is_active], $this->correlationId());

            return $publication;
        });
    }

    /** @return array<string, mixed> */
    private function aggregate(string $start, string $end, ?int $rtId, bool $publicOnly = false): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1 || $start >= $end) {
            throw ValidationException::withMessages(['period' => 'Periode statistik tidak valid.']);
        }
        $query = Deposit::query()->with('items.wasteType')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->whereDate('occurred_at', '>=', $start)->whereDate('occurred_at', '<', $end);
        if ($rtId !== null) {
            $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId));
        }
        $deposits = $query->get();
        $subjects = $deposits->pluck('customer_id')->unique()->count();
        $weight = 0.0;
        $plastic = 0.0;
        $types = [];
        foreach ($deposits as $deposit) {
            foreach ($deposit->items as $item) {
                $current = (float) $item->weight_kg;
                $weight += $current;
                $typeName = (string) ($item->wasteType->name ?? $item->waste_type_name ?? '');
                if ($typeName !== '') {
                    $types[$typeName] = ($types[$typeName] ?? 0.0) + $current;
                }
                if ($item->wasteType?->is_plastic === true) {
                    $plastic += $current;
                }
            }
        }
        arsort($types);

        $activeCustomers = User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('customerProfile')
            ->when($rtId !== null, static fn (Builder $customers): Builder => $customers->whereHas('customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId)))
            ->count();
        $targetProgress = $this->targetProgress($start, $end, $rtId, $publicOnly);
        $mobileServiceCount = MobileService::query()
            ->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open, MobileServiceStatus::Closed])
            ->whereDate('starts_at', '>=', $start)
            ->whereDate('starts_at', '<', $end)
            ->when($rtId !== null, static fn (Builder $services): Builder => $services->where('rt_id', $rtId))
            ->count();

        return [
            'suppressed' => false,
            'subject_count' => $subjects,
            'active_customers' => $activeCustomers,
            'deposit_count' => $deposits->count(),
            'total_weight_kg' => number_format($weight, 3, '.', ''),
            'plastic_weight_kg' => number_format($plastic, 3, '.', ''),
            'dominant_waste_type' => array_key_first($types) ?? 'Tidak teridentifikasi',
            'target_progress_kg' => number_format($targetProgress, 3, '.', ''),
            'mobile_service_count' => $mobileServiceCount,
        ];
    }

    /**
     * @return array{total_weight_kg: list<array{month: string, total_weight_kg: string}>, dominant_waste_type: list<array{waste_type: string, weight_kg: string}>, target_progress_kg: list<array{target_number: string, name: string, target_weight_kg: string, progress_kg: string}>}
     */
    private function charts(string $start, string $end, ?int $rtId, ?int $threshold = null, bool $publicOnly = false): array
    {
        $deposits = Deposit::query()
            ->with('items.wasteType')
            ->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])
            ->whereDate('occurred_at', '>=', $start)
            ->whereDate('occurred_at', '<', $end)
            ->when($rtId !== null, static fn (Builder $query): Builder => $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId)))
            ->get();
        $monthly = [];
        $composition = [];
        foreach ($deposits as $deposit) {
            $month = $deposit->occurred_at->format('Y-m');
            $monthly[$month]['subjects'][$deposit->customer_id] = true;
            $monthly[$month]['weight'] = ($monthly[$month]['weight'] ?? 0.0) + (float) $deposit->items->sum('weight_kg');
            foreach ($deposit->items as $item) {
                $name = (string) ($item->wasteType->name ?? $item->waste_type_name ?? '');
                if ($name === '') {
                    continue;
                }
                $composition[$name]['subjects'][$deposit->customer_id] = true;
                $composition[$name]['weight'] = ($composition[$name]['weight'] ?? 0.0) + (float) $item->weight_kg;
            }
        }

        $months = [];
        $cursor = new \DateTimeImmutable($start);
        $endDate = new \DateTimeImmutable($end);
        while ($cursor < $endDate) {
            $month = $cursor->format('Y-m');
            $bucket = $monthly[$month] ?? ['subjects' => [], 'weight' => 0.0];
            if ($threshold === null || count($bucket['subjects']) >= $threshold) {
                $months[] = ['month' => $month, 'total_weight_kg' => number_format($bucket['weight'], 3, '.', '')];
            }
            $cursor = $cursor->modify('first day of next month');
        }

        $segments = collect($composition)
            ->filter(static fn (array $segment): bool => $threshold === null || count($segment['subjects']) >= $threshold)
            ->sortByDesc('weight')
            ->map(static fn (array $segment, string $name): array => ['waste_type' => $name, 'weight_kg' => number_format($segment['weight'], 3, '.', '')])
            ->values()
            ->all();

        $targets = $this->chartTargets($start, $end, $rtId, $publicOnly, $threshold);

        return [
            'total_weight_kg' => $months,
            'dominant_waste_type' => $segments,
            'target_progress_kg' => $targets,
        ];
    }

    /** @return list<array{target_number: string, name: string, target_weight_kg: string, progress_kg: string}> */
    private function chartTargets(string $start, string $end, ?int $rtId, bool $publicOnly, ?int $threshold): array
    {
        $targets = CollectionTarget::query()
            ->with('scopes')
            ->whereIn('status', [TargetStatus::Active, TargetStatus::Closed])
            ->whereDate('period_start', '<', $end)
            ->whereDate('period_end', '>=', $start)
            ->when($publicOnly, static fn (Builder $query): Builder => $query->where('is_public', true))
            ->get();
        $progress = $this->targetProgress->aggregateMany($targets, $rtId === null ? null : [$rtId]);

        return $targets->filter(function (CollectionTarget $target) use ($progress, $publicOnly, $threshold): bool {
            $subjects = $progress[$target->getKey()]['subject_count'] ?? 0;

            return ! $publicOnly || $subjects >= max($threshold ?? 0, $target->public_min_subjects);
        })->map(function (CollectionTarget $target) use ($progress): array {
            $progressKg = $target->status === TargetStatus::Closed
                ? $this->targetProgress->progress($target)
                : $progress[$target->getKey()]['weight_kg'] ?? '0.000';

            return [
                'target_number' => $target->target_number,
                'name' => $target->name,
                'target_weight_kg' => number_format((float) $target->target_weight_kg, 3, '.', ''),
                'progress_kg' => $progressKg,
            ];
        })->values()->all();
    }

    private function targetProgress(string $start, string $end, ?int $rtId, bool $publicOnly): float
    {
        $targets = CollectionTarget::query()
            ->with('scopes')
            ->whereIn('status', [TargetStatus::Active, TargetStatus::Closed])
            ->whereDate('period_start', '<', $end)
            ->whereDate('period_end', '>=', $start)
            ->when($publicOnly, static fn (Builder $query): Builder => $query->where('is_public', true))
            ->get();

        return (float) $targets->sum(function (CollectionTarget $target) use ($rtId): float {
            if ($rtId === null) {
                return (float) $this->targetProgress->progress($target);
            }

            return (float) $this->targetProgress->progressForRtIds($target, [$rtId]);
        });
    }

    private function canViewRegion(User $actor, int $rtId): bool
    {
        return $this->visibleUsers->canAccessCustomerRt($actor, $rtId);
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses statistik internal.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
