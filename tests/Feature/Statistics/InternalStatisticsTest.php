<?php

declare(strict_types=1);

namespace Tests\Feature\Statistics;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Statistics\Services\StatisticsService;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Livewire\PublicSite\PublicPrograms;
use App\Livewire\Statistics\InternalDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class InternalStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_and_public_statistics_calculate_every_configurable_metric(): void
    {
        config(['app.statistics_privacy_threshold' => 1]);
        $actor = $this->userWith('statistics.internal.view', 'statistics.public.manage', 'user.view.all', 'waste.manage');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        [$type, $condition] = $this->wasteType($actor);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-STATS-001',
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-01 10:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.250',
            'total_value' => 12_000,
            'finalized_at' => '2026-08-01 10:00:00',
        ]);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);
        $customerTwo = User::factory()->create();
        CustomerProfile::factory()->for($customerTwo)->create();
        $depositTwo = Deposit::query()->create([
            'deposit_number' => 'DEP-STATS-002',
            'customer_id' => $customerTwo->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => '2026-08-01 11:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.250',
            'total_value' => 12_000,
            'finalized_at' => '2026-08-01 11:00:00',
        ]);
        DepositItem::query()->create(['deposit_id' => $depositTwo->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.250']);
        CollectionTarget::query()->create([
            'target_number' => 'TGT-STATS-001',
            'name' => 'Target statistik',
            'purpose' => 'Uji metrik target',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'target_weight_kg' => '10.000',
            'status' => TargetStatus::Active,
            'is_public' => true,
            'public_min_subjects' => 1,
            'created_by' => $actor->id,
        ]);
        MobileService::query()->create([
            'service_number' => 'MOB-STATS-001',
            'point' => 'Titik statistik',
            'starts_at' => '2026-08-01 08:00:00',
            'ends_at' => '2026-08-01 12:00:00',
            'status' => MobileServiceStatus::Closed,
            'capacity' => 20,
            'served_count' => 1,
            'created_by' => $actor->id,
        ]);

        $statistics = app(StatisticsService::class);
        $internal = $statistics->internal($actor, '2026-08-01', '2026-08-02');

        self::assertFalse($internal['suppressed']);
        self::assertSame(2, $internal['active_customers']);
        self::assertSame(2, $internal['deposit_count']);
        self::assertSame('2.500', $internal['total_weight_kg']);
        self::assertSame('2.500', $internal['plastic_weight_kg']);
        self::assertSame('Plastik', $internal['dominant_waste_type']);
        self::assertSame('2.500', $internal['target_progress_kg']);
        self::assertSame(1, $internal['mobile_service_count']);
        self::assertSame([
            'total_weight_kg' => [['month' => '2026-08', 'total_weight_kg' => '2.500']],
            'dominant_waste_type' => [['waste_type' => 'Plastik', 'weight_kg' => '2.500']],
            'target_progress_kg' => [[
                'target_number' => 'TGT-STATS-001',
                'name' => 'Target statistik',
                'target_weight_kg' => '10.000',
                'progress_kg' => '2.500',
            ]],
        ], $internal['charts']);

        $statistics->configurePublic($actor, ['active_customers', 'total_weight_kg', 'dominant_waste_type', 'target_progress_kg', 'mobile_service_count'], ['period'], 2, true);
        $public = $statistics->public('2026-08-01', '2026-08-02');

        self::assertFalse($public['suppressed']);
        self::assertSame(['active_customers' => 2, 'total_weight_kg' => '2.500', 'dominant_waste_type' => 'Plastik', 'target_progress_kg' => '2.500', 'mobile_service_count' => 1], $public['metrics']);
        self::assertSame([
            'total_weight_kg' => [['month' => '2026-08', 'total_weight_kg' => '2.500']],
            'dominant_waste_type' => [['waste_type' => 'Plastik', 'weight_kg' => '2.500']],
            'target_progress_kg' => [[
                'target_number' => 'TGT-STATS-001',
                'name' => 'Target statistik',
                'target_weight_kg' => '10.000',
                'progress_kg' => '2.500',
            ]],
        ], $public['charts']);
    }

    public function test_public_charts_independently_suppress_under_threshold_buckets_segments_and_targets(): void
    {
        config(['app.statistics_privacy_threshold' => 2]);
        $actor = $this->userWith('statistics.internal.view', 'statistics.public.manage', 'user.view.all', 'waste.manage');
        [$plastic, $condition] = $this->wasteType($actor);
        $paper = app(ManageWasteMaster::class)->createType($actor, $plastic->category, $plastic->unit, 'STATS-PAPER-'.$actor->id, 'Kertas', null, 0, false, true, [$condition->id]);
        $dusun = Dusun::query()->create(['code' => 'CHART-DS-1', 'name' => 'Dusun Grafik', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'CHART-RW-1', 'name' => 'RW Grafik', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'CHART-RT-1', 'name' => 'RT Grafik', 'is_active' => true]);

        foreach ([['2026-08-01 10:00:00', $plastic, 'DEP-CHART-1'], ['2026-08-02 10:00:00', $plastic, 'DEP-CHART-2'], ['2026-09-01 10:00:00', $paper, 'DEP-CHART-3']] as [$occurredAt, $type, $number]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => $occurredAt, 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 1_000, 'finalized_at' => $occurredAt]);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000']);
        }

        $eligibleTarget = CollectionTarget::query()->create(['target_number' => 'TGT-CHART-ELIGIBLE', 'name' => 'Target aman', 'purpose' => 'Uji target aman', 'period_start' => '2026-08-01', 'period_end' => '2026-09-30', 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 2, 'created_by' => $actor->id]);
        TargetScope::query()->create(['collection_target_id' => $eligibleTarget->id, 'rt_id' => $rt->id, 'waste_type_id' => $plastic->id]);
        $privateTarget = CollectionTarget::query()->create(['target_number' => 'TGT-CHART-PRIVATE', 'name' => 'Target rahasia', 'purpose' => 'Uji target rahasia', 'period_start' => '2026-08-01', 'period_end' => '2026-09-30', 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 3, 'created_by' => $actor->id]);
        TargetScope::query()->create(['collection_target_id' => $privateTarget->id, 'rt_id' => $rt->id, 'waste_type_id' => $plastic->id]);

        $statistics = app(StatisticsService::class);
        $statistics->configurePublic($actor, ['total_weight_kg', 'dominant_waste_type', 'target_progress_kg'], ['period', 'rt_id'], 2, true);
        $public = $statistics->public('2026-08-01', '2026-10-01', $rt->id);

        self::assertFalse($public['suppressed']);
        self::assertSame('2.000', $public['metrics']['target_progress_kg']);
        self::assertSame([['month' => '2026-08', 'total_weight_kg' => '2.000']], $public['charts']['total_weight_kg']);
        self::assertSame([['waste_type' => 'Plastik', 'weight_kg' => '2.000']], $public['charts']['dominant_waste_type']);
        self::assertSame([['target_number' => 'TGT-CHART-ELIGIBLE', 'name' => 'Target aman', 'target_weight_kg' => '10.000', 'progress_kg' => '2.000']], $public['charts']['target_progress_kg']);
        self::assertStringNotContainsString('TGT-CHART-PRIVATE', json_encode($public['charts'], JSON_THROW_ON_ERROR));
    }

    public function test_closed_target_chart_and_public_kpi_use_closed_progress_snapshot(): void
    {
        config(['app.statistics_privacy_threshold' => 2]);
        $actor = $this->userWith('statistics.internal.view', 'statistics.public.manage', 'user.view.all', 'waste.manage');
        [$type, $condition] = $this->wasteType($actor);
        foreach (['DEP-CLOSED-CHART-1', 'DEP-CLOSED-CHART-2'] as $number) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create();
            $deposit = Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 1_000, 'finalized_at' => '2026-08-01 10:00:00']);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000']);
        }
        CollectionTarget::query()->create(['target_number' => 'TGT-CLOSED-CHART', 'name' => 'Target ditutup', 'purpose' => 'Uji snapshot', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'target_weight_kg' => '10.000', 'status' => TargetStatus::Closed, 'is_public' => true, 'public_min_subjects' => 2, 'created_by' => $actor->id, 'closed_at' => '2026-08-31 12:00:00', 'closed_progress_kg' => '9.000']);

        $statistics = app(StatisticsService::class);
        $internal = $statistics->internal($actor, '2026-08-01', '2026-09-01');
        self::assertSame('9.000', $internal['charts']['target_progress_kg'][0]['progress_kg']);

        $statistics->configurePublic($actor, ['target_progress_kg'], ['period'], 2, true);
        $public = $statistics->public('2026-08-01', '2026-09-01');
        self::assertSame('9.000', $public['metrics']['target_progress_kg']);
        self::assertSame('9.000', $public['charts']['target_progress_kg'][0]['progress_kg']);
    }

    public function test_public_statistics_uses_configured_rt_aggregation_and_includes_period_metadata(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'STATS-DS-1', 'name' => 'Dusun Statistik', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'STATS-RW-1', 'name' => 'RW Statistik', 'is_active' => true]);
        $selectedRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'STATS-RT-1', 'name' => 'RT Terpilih', 'is_active' => true]);
        $otherRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'STATS-RT-2', 'name' => 'RT Lain', 'is_active' => true]);
        [$type, $condition] = $this->wasteType($actor);

        foreach ([[$selectedRt, 'DEP-RT-SELECTED-1'], [$selectedRt, 'DEP-RT-SELECTED-2'], [$otherRt, 'DEP-RT-OTHER']] as [$rt, $number]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 1_000, 'finalized_at' => '2026-08-01 10:00:00']);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000']);
        }

        app(StatisticsService::class)->configurePublic($actor, ['deposit_count', 'total_weight_kg'], ['period', 'rt_id'], 2, true);
        $result = app(StatisticsService::class)->public('2026-08-01', '2026-08-02', $selectedRt->id);

        self::assertFalse($result['suppressed']);
        self::assertSame(['deposit_count' => 2, 'total_weight_kg' => '2.000'], $result['metrics']);
        self::assertSame(['start' => '2026-08-01', 'end' => '2026-08-02'], $result['period']);
        self::assertSame($selectedRt->id, $result['rt_id']);
    }

    public function test_public_programs_render_human_readable_scope_date_range_metric_labels_and_weight_units(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'PROGRAM-DS-1', 'name' => 'Dusun Program', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'PROGRAM-RW-1', 'name' => 'RW Program', 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'PROGRAM-RT-1', 'name' => 'RT Program', 'is_active' => true]);
        [$type] = $this->wasteType($actor);
        $target = CollectionTarget::query()->create(['target_number' => 'TGT-PUBLIC-1', 'name' => 'Target publik', 'purpose' => 'Pengumpulan terarah', 'period_start' => today()->subDay(), 'period_end' => today()->addDay(), 'target_weight_kg' => '5.000', 'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 0, 'created_by' => $actor->id]);
        TargetScope::query()->create(['collection_target_id' => $target->id, 'rt_id' => $rt->id, 'waste_type_id' => $type->id]);
        foreach (['DEP-PUBLIC-PROGRAM-1', 'DEP-PUBLIC-PROGRAM-2'] as $number) {
            $customer = User::factory()->create();
            Deposit::query()->create(['deposit_number' => $number, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '0.000', 'total_value' => 0, 'finalized_at' => now()]);
        }
        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Cakupan:')
            ->assertSee('RT Program')
            ->assertSee($type->name)
            ->assertSee('Periode 12 bulan:')
            ->assertSee('Total berat')
            ->assertDontSee('Total weight kg');
    }

    public function test_public_programs_render_only_published_charts_and_explain_empty_chart_data(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        [$plastic, $condition] = $this->wasteType($actor);
        $paper = app(ManageWasteMaster::class)->createType($actor, $plastic->category, $plastic->unit, 'PUBLIC-CHART-PAPER-'.$actor->id, 'Kertas', null, 0, false, true, [$condition->id]);

        foreach ([[now()->subMonth(), $plastic, '1.500', 'PLASTIC-1'], [now()->subMonth(), $plastic, '1.500', 'PLASTIC-2'], [now(), $paper, '2.500', 'PAPER-1'], [now(), $paper, '2.500', 'PAPER-2']] as [$occurredAt, $type, $weight, $suffix]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create();
            $deposit = Deposit::query()->create(['deposit_number' => 'DEP-PUBLIC-CHART-'.$suffix, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => $occurredAt, 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => $weight, 'total_value' => 1_000, 'finalized_at' => $occurredAt]);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => $weight]);
        }

        CollectionTarget::query()->create(['target_number' => 'TGT-PUBLIC-CHART', 'name' => 'Target visual publik', 'purpose' => 'Uji visual statistik', 'period_start' => today()->subMonth(), 'period_end' => today()->addMonth(), 'target_weight_kg' => '10.000', 'status' => TargetStatus::Active, 'is_public' => true, 'public_min_subjects' => 2, 'created_by' => $actor->id]);
        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg', 'dominant_waste_type', 'target_progress_kg'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Tren berat terkumpul')
            ->assertSee('Komposisi sampah')
            ->assertSee('Progres target publik')
            ->assertSee('Kertas')
            ->assertSee('Target visual publik')
            ->assertSeeHtml('<progress')
            ->assertSeeHtml('<svg');

        app(StatisticsService::class)->configurePublic($actor, ['active_customers'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Belum ada data visual yang dapat dipublikasikan untuk periode ini.')
            ->assertDontSee('Tren berat terkumpul');
    }

    public function test_public_programs_applies_selected_rt_to_public_statistics_only_when_configured(): void
    {
        $actor = $this->userWith('statistics.public.manage', 'waste.manage');
        $dusun = Dusun::query()->create(['code' => 'FILTER-DS-1', 'name' => 'Dusun Filter', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'FILTER-RW-1', 'name' => 'RW Filter', 'is_active' => true]);
        $firstRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'FILTER-RT-1', 'name' => 'RT Satu', 'is_active' => true]);
        $secondRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'FILTER-RT-2', 'name' => 'RT Dua', 'is_active' => true]);
        [$type, $condition] = $this->wasteType($actor);

        foreach ([[$firstRt, '1.000', 'SATU-1'], [$firstRt, '1.000', 'SATU-2'], [$secondRt, '2.000', 'DUA-1'], [$secondRt, '2.000', 'DUA-2']] as [$rt, $weight, $suffix]) {
            $customer = User::factory()->create();
            CustomerProfile::factory()->for($customer)->create(['rt_id' => $rt->id]);
            $deposit = Deposit::query()->create(['deposit_number' => 'DEP-FILTER-'.$suffix, 'customer_id' => $customer->id, 'staff_id' => $actor->id, 'method' => 'langsung', 'occurred_at' => now(), 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => $weight, 'total_value' => 1_000, 'finalized_at' => now()]);
            DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => $weight]);
        }

        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period', 'rt_id'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertSee('Cakupan statistik')
            ->assertSee('Seluruh desa')
            ->assertSee('RT Satu')
            ->assertSee('RT Dua')
            ->set('rtId', (string) $firstRt->id)
            ->assertSee('Menampilkan agregat untuk RT terpilih.')
            ->assertSee('2 kg')
            ->set('rtId', (string) $secondRt->id)
            ->assertSee('4 kg');

        app(StatisticsService::class)->configurePublic($actor, ['total_weight_kg'], ['period'], 2, true);

        Livewire::test(PublicPrograms::class)
            ->assertDontSee('Cakupan statistik')
            ->assertSee('6 kg');
    }

    public function test_internal_dashboard_renders_chart_payload_with_accessible_values_and_empty_states(): void
    {
        $actor = $this->userWith('statistics.internal.view', 'user.view.all');

        Livewire::actingAs($actor)
            ->test(InternalDashboard::class)
            ->set('statistics', [
                'suppressed' => false,
                'active_customers' => 2,
                'deposit_count' => 2,
                'total_weight_kg' => '2.500',
                'plastic_weight_kg' => '2.500',
                'target_progress_kg' => '2.500',
                'mobile_service_count' => 0,
                'subject_count' => 2,
                'dominant_waste_type' => 'Plastik',
                'charts' => [
                    'total_weight_kg' => [['month' => '2026-08', 'total_weight_kg' => '2.500']],
                    'dominant_waste_type' => [
                        ['waste_type' => 'Plastik', 'weight_kg' => '2.500'],
                        ['waste_type' => 'Kertas', 'weight_kg' => '7.500'],
                    ],
                    'target_progress_kg' => [['target_number' => 'TGT-UI-001', 'name' => 'Target tampilan', 'target_weight_kg' => '10.000', 'progress_kg' => '2.500']],
                ],
            ])
            ->assertSee('Tren berat setoran')
            ->assertSee('2026-08')
            ->assertSee('Komposisi jenis sampah')
            ->assertSee('Plastik')
            ->assertSee('Kertas')
            ->assertSee('stroke-dasharray', false)
            ->assertSee('text-sun-500', false)
            ->assertSee('Progres per target')
            ->assertSee('Target tampilan')
            ->assertSee('TGT-UI-001')
            ->assertSee('2,5 dari 10 kg (25,0%)');

        Livewire::actingAs($actor)
            ->test(InternalDashboard::class)
            ->set('statistics', [
                'suppressed' => false,
                'charts' => [],
            ])
            ->assertSee('Belum ada data tren untuk filter ini.')
            ->assertSee('Belum ada komposisi sampah untuk filter ini.')
            ->assertSee('Belum ada target yang relevan untuk filter ini.');
    }

    public function test_internal_statistics_suppression_clears_new_metrics_too(): void
    {
        $actor = $this->userWith('statistics.internal.view', 'user.view.all');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        config(['app.statistics_privacy_threshold' => 5]);

        $result = app(StatisticsService::class)->internal($actor, '2026-08-01', '2026-08-02');

        self::assertTrue($result['suppressed']);
        self::assertNull($result['active_customers']);
        self::assertNull($result['target_progress_kg']);
        self::assertNull($result['mobile_service_count']);
    }

    /** @return array{WasteType, WasteCondition} */
    private function wasteType(User $actor): array
    {
        $master = app(ManageWasteMaster::class);
        $category = $master->createCategory($actor, 'STATS-CAT-'.$actor->id, 'Statistik');
        $unit = $master->createUnit($actor, 'STATS-UNIT-'.$actor->id, 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $condition = $master->createCondition($actor, 'STATS-COND-'.$actor->id, 'Bersih', null);
        $type = $master->createType($actor, $category, $unit, 'STATS-TYPE-'.$actor->id, 'Plastik', null, 0, true, true, [$condition->id]);

        return [$type, $condition];
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'stats-test-'.$user->id, 'description' => 'Statistics test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
