<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupItem;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Models\StatusHistory;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Shared\Weight;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Builds a realistic local/staging dataset for manual end-to-end testing.
 *
 * This is deliberately not called in production. All business identifiers use
 * a DEMO prefix and every lookup is idempotent, so re-running db:seed is safe.
 */
final class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo#Sindangheula2026';

    /** @var list<string> */
    private const CUSTOMER_NAMES = [
        'Asep Saepuloh', 'Dewi Lestari', 'Rina Marlina', 'Dadan Hidayat', 'Siti Aminah', 'Ujang Suherman',
        'Nia Kurniasih', 'Fajar Nugraha', 'Yani Mulyani', 'Agus Setiawan', 'Lilis Suryani', 'Rudi Hartono',
        'Euis Komariah', 'Budi Santoso', 'Tati Rosdiana', 'Hendra Gunawan', 'Wulan Sari', 'Deni Permana',
        'Yayah Rohayah', 'Iwan Kurniawan', 'Maman Suparman', 'Novi Andriani', 'Cecep Saefulloh', 'Fitri Handayani',
        'Taufik Hidayat', 'Mira Puspitasari', 'Dede Suhendar', 'Salsa Nuraini', 'Rian Firmansyah', 'Endah Wulandari',
        'Rizal Maulana', 'Neng Sulastri', 'Wahyu Ramadhan', 'Intan Permata', 'Yudi Irawan', 'Mela Anggraini',
        'Raka Pratama', 'Nurhayati', 'Dimas Saputra', 'Sari Rahmawati', 'Aldi Firmansyah', 'Mutiara Fitri',
        'Robby Kurnia', 'Novianti', 'Gilang Ramadhan', 'Tina Kartika', 'Solehudin', 'Maya Safitri',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder dilewati pada production.');

            return;
        }

        $now = CarbonImmutable::now('Asia/Jakarta');
        $admin = User::query()->where('email', DeveloperUsersSeeder::email('admin'))->firstOrFail();
        $regions = $this->seedRegions($admin);
        $staff = $this->seedStaff($regions['areas']);
        $customers = $this->seedCustomers($regions['rts']);
        $master = $this->seedWasteMaster($admin);
        $prices = $this->seedPrices($master['types'], $master['conditions'], $admin, $now);
        $mobileServices = $this->seedMobileServices($regions, $staff, $master['types'], $now);
        $pickups = $this->seedPickups($regions, $staff, $customers, $master['types'], $now);

        DB::transaction(function () use ($admin, $customers, $staff, $master, $prices, $mobileServices, $pickups, $regions, $now): void {
            $ledger = app(LedgerService::class);
            foreach ($customers as $customer) {
                $ledger->ensureAccount($customer);
            }

            $this->seedDeposits($customers, $staff, $master['types'], $master['conditions'], $prices, $mobileServices, $pickups, $ledger, $now);
            $this->seedWithdrawals($customers, $staff, $ledger, $now);
            $this->seedGroceries($customers, $staff, $ledger, $now);
            $this->seedPrograms($admin, $regions['rts'], $master, $now);
            $this->seedAnnouncements($admin, $regions['rts'], $now);
            $this->seedStatisticPublication($admin);
        });

        $this->command?->info('Demo data siap: '.count($customers).' warga, '.count($regions['rts']).' RT, '.count($master['types']).' jenis sampah, dan histori transaksi 7 hari.');
        $this->command?->info('Password akun demo tambahan: '.self::DEMO_PASSWORD);
    }

    /** @return array{dusuns: list<Dusun>, rws: list<Rw>, rts: list<Rt>, areas: list<ServiceArea>} */
    private function seedRegions(User $admin): array
    {
        $manager = app(ManageRegions::class);
        $dusuns = [];
        $rws = [];
        $rts = [];

        foreach ([
            ['code' => 'DSN-DEMO-UTARA', 'name' => 'Dusun Sindangheula Utara'],
            ['code' => 'DSN-DEMO-TENGAH', 'name' => 'Dusun Sindangheula Tengah'],
            ['code' => 'DSN-DEMO-SELATAN', 'name' => 'Dusun Sindangheula Selatan'],
        ] as $dusunData) {
            $dusun = Dusun::query()->where('code', $dusunData['code'])->first();
            $dusun ??= $manager->createDusun($admin, $dusunData['code'], $dusunData['name']);
            $dusuns[] = $dusun;

            foreach (range(1, 2) as $rwNumber) {
                $rwCode = $dusunData['code'].'-RW-'.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT);
                $rw = Rw::query()->where('dusun_id', $dusun->id)->where('code', $rwCode)->first();
                $rw ??= $manager->createRw($admin, $dusun, $rwCode, 'RW '.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT).' '.$dusun->name);
                $rws[] = $rw;

                foreach (range(1, 3) as $rtNumber) {
                    $rtCode = $rwCode.'-RT-'.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT);
                    $rt = Rt::query()->where('rw_id', $rw->id)->where('code', $rtCode)->first();
                    $rt ??= $manager->createRt($admin, $rw, $rtCode, 'RT '.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT).' '.$rw->name);
                    $rts[] = $rt;
                }
            }
        }

        $areas = [];
        foreach (array_chunk($rts, 6) as $index => $areaRts) {
            $name = 'Area Layanan Demo '.($index + 1);
            $area = ServiceArea::query()->where('name', $name)->first();
            if ($area === null) {
                $area = $manager->createServiceArea($admin, $name, $areaRts);
            } else {
                $manager->updateServiceArea($admin, $area, $name, $areaRts);
            }
            $areas[] = $area;
        }

        return ['dusuns' => $dusuns, 'rws' => $rws, 'rts' => $rts, 'areas' => $areas];
    }

    /** @param list<ServiceArea> $areas
     * @return list<User>
     */
    private function seedStaff(array $areas): array
    {
        $role = Role::query()->where('name', 'petugas')->firstOrFail();
        $staff = [];
        foreach (range(1, 4) as $number) {
            $phone = '628130000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $user = User::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'name' => 'Petugas Demo '.$number,
                    'email' => 'petugas.demo.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@sindangheula.test',
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'status' => UserStatus::Active,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Demo dataset']]);
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['staff_number' => 'STF-DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'service_area_id' => $areas[($number - 1) % count($areas)]->id, 'active_from' => today()->subDays(30), 'active_to' => null],
            );
            $staff[] = $user;
        }

        $treasurer = User::query()->where('email', DeveloperUsersSeeder::email('bendahara'))->firstOrFail();
        $staff[] = $treasurer;

        return $staff;
    }

    /** @param list<Rt> $rts
     * @return list<User>
     */
    private function seedCustomers(array $rts): array
    {
        $role = Role::query()->where('name', 'warga')->firstOrFail();
        $customers = [];
        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $number = $index + 1;
            $user = User::query()->updateOrCreate(
                ['phone' => '628140000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'email' => 'warga.demo.'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'@sindangheula.test',
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'status' => UserStatus::Active,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Demo dataset']]);
            $profile = CustomerProfile::query()->firstOrNew(['user_id' => $user->id]);
            $token = $profile->qr_token_hash === null ? QrToken::generate() : null;
            $profile->forceFill([
                'customer_number' => $profile->customer_number ?? 'CST-DEMO-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'rt_id' => $rts[$index % count($rts)]->id,
                'address' => 'Kampung Demo '.(($index % 8) + 1).', Sindangheula',
                'joined_at' => today()->subDays(7 - ($index % 7)),
                'qr_token_hash' => $profile->qr_token_hash ?? $token?->hash(),
                'qr_token_encrypted' => $profile->qr_token_encrypted ?? $token?->value(),
                'qr_rotated_at' => $profile->qr_rotated_at ?? now(),
            ])->save();
            $customers[] = $user;
        }

        return $customers;
    }

    /** @return array{categories: list<WasteCategory>, types: list<WasteType>, conditions: list<WasteCondition>} */
    private function seedWasteMaster(User $admin): array
    {
        $manager = app(ManageWasteMaster::class);
        $categoryDefinitions = [
            ['code' => 'PLASTIK', 'name' => 'Plastik'],
            ['code' => 'KERTAS', 'name' => 'Kertas'],
            ['code' => 'LOGAM', 'name' => 'Logam'],
            ['code' => 'KACA', 'name' => 'Kaca'],
        ];
        $categories = [];
        foreach ($categoryDefinitions as $definition) {
            $category = WasteCategory::query()->where('code', $definition['code'])->first();
            $category ??= $manager->createCategory($admin, $definition['code'], $definition['name']);
            $categories[$definition['code']] = $category;
        }

        $unit = WasteUnit::query()->where('code', 'KG')->first();
        $unit ??= $manager->createUnit($admin, 'KG', 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        $conditions = [];
        foreach ([['code' => 'BERSIH', 'name' => 'Bersih'], ['code' => 'CAMPUR', 'name' => 'Campur']] as $definition) {
            $condition = WasteCondition::query()->where('code', $definition['code'])->first();
            $condition ??= $manager->createCondition($admin, $definition['code'], $definition['name'], 'Kondisi material demo.');
            $conditions[$definition['code']] = $condition;
        }

        $types = [];
        $typeDefinitions = [
            ['category' => 'PLASTIK', 'code' => 'PET-BOTOL', 'name' => 'Botol PET', 'plastic' => true],
            ['category' => 'PLASTIK', 'code' => 'GELAS-PLASTIK', 'name' => 'Gelas Plastik', 'plastic' => true],
            ['category' => 'PLASTIK', 'code' => 'PLASTIK-CAMPUR', 'name' => 'Plastik Campur', 'plastic' => true],
            ['category' => 'KERTAS', 'code' => 'KARDUS', 'name' => 'Kardus', 'plastic' => false],
            ['category' => 'KERTAS', 'code' => 'KERTAS-PUTIH', 'name' => 'Kertas Putih', 'plastic' => false],
            ['category' => 'LOGAM', 'code' => 'BESI', 'name' => 'Besi', 'plastic' => false],
            ['category' => 'LOGAM', 'code' => 'ALUMINIUM', 'name' => 'Aluminium', 'plastic' => false],
            ['category' => 'KACA', 'code' => 'KACA-BOTOL', 'name' => 'Botol Kaca', 'plastic' => false],
        ];
        foreach ($typeDefinitions as $index => $definition) {
            $type = WasteType::query()->where('code', $definition['code'])->first();
            if ($type === null) {
                $type = $manager->createType($admin, $categories[$definition['category']], $unit, $definition['code'], $definition['name'], 'Material demo untuk pengujian alur setoran.', $index + 1, $definition['plastic'], true, array_values(array_map(static fn (WasteCondition $condition): int => $condition->id, $conditions)));
            }
            $types[] = $type;
        }

        return ['categories' => array_values($categories), 'types' => $types, 'conditions' => array_values($conditions)];
    }

    /** @param list<WasteType> $types
     * @param  list<WasteCondition>  $conditions
     * @return array<int, array<int, WastePrice>>
     */
    private function seedPrices(array $types, array $conditions, User $admin, CarbonImmutable $now): array
    {
        $basePrices = [1800, 2200, 2600, 1500, 1800, 3000, 9000, 1200];
        $prices = [];
        foreach ($types as $typeIndex => $type) {
            foreach ($conditions as $conditionIndex => $condition) {
                $priceValue = $basePrices[$typeIndex] - ($conditionIndex * 250);
                $from = $now->subDays(30)->startOfDay();
                $price = WastePrice::query()
                    ->where('waste_type_id', $type->id)
                    ->where('waste_condition_id', $condition->id)
                    ->where('effective_from', $from)
                    ->first();
                if ($price === null) {
                    $price = WasteMasterMutationGuard::run(fn (): WastePrice => WastePrice::query()->create([
                        'waste_type_id' => $type->id,
                        'waste_condition_id' => $condition->id,
                        'price' => max(500, $priceValue),
                        'effective_from' => $from,
                        'effective_to' => null,
                        'created_by' => $admin->id,
                        'rounding_version' => 'half_up_v1',
                    ]));
                }
                $prices[$type->id][$condition->id] = $price;
            }
        }

        return $prices;
    }

    /** @param array{rws: list<Rw>, rts: list<Rt>} $regions
     * @param  list<User>  $staff
     * @param  list<WasteType>  $types
     * @return list<MobileService>
     */
    private function seedMobileServices(array $regions, array $staff, array $types, CarbonImmutable $now): array
    {
        $services = [];
        $definitions = [
            ['number' => 'MOB-DEMO-001', 'start' => $now->subDays(6)->setTime(8, 0), 'end' => $now->subDays(6)->setTime(12, 0), 'status' => MobileServiceStatus::Closed, 'point' => 'Balai Dusun Utara'],
            ['number' => 'MOB-DEMO-002', 'start' => $now->subDays(3)->setTime(8, 0), 'end' => $now->subDays(3)->setTime(13, 0), 'status' => MobileServiceStatus::Closed, 'point' => 'Lapangan Dusun Tengah'],
            ['number' => 'MOB-DEMO-003', 'start' => $now->subHour(), 'end' => $now->addHours(4), 'status' => MobileServiceStatus::Open, 'point' => 'Halaman Kantor Desa'],
            ['number' => 'MOB-DEMO-004', 'start' => $now->addDay()->setTime(8, 0), 'end' => $now->addDay()->setTime(13, 0), 'status' => MobileServiceStatus::Published, 'point' => 'Balai Dusun Selatan'],
        ];
        foreach ($definitions as $index => $definition) {
            $service = MobileService::query()->firstOrCreate(
                ['service_number' => $definition['number']],
                [
                    'rw_id' => $regions['rws'][$index % count($regions['rws'])]->id,
                    'rt_id' => $regions['rts'][$index % count($regions['rts'])]->id,
                    'point' => $definition['point'],
                    'starts_at' => $definition['start'],
                    'ends_at' => $definition['end'],
                    'status' => $definition['status'],
                    'capacity' => 30,
                    'served_count' => $definition['status'] === MobileServiceStatus::Closed ? 12 + $index : 0,
                    'notes' => 'Jadwal demo untuk pengujian layanan keliling.',
                    'created_by' => $staff[0]->id,
                ],
            );
            $service->staff()->syncWithoutDetaching([$staff[$index % 4]->id]);
            $service->wasteTypes()->syncWithoutDetaching(array_map(static fn (WasteType $type): int => $type->id, array_slice($types, 0, 6)));
            $services[] = $service;
        }

        return $services;
    }

    /** @param array{areas: list<ServiceArea>, rts: list<Rt>} $regions
     * @param  list<User>  $staff
     * @param  list<User>  $customers
     * @param  list<WasteType>  $types
     * @return list<PickupRequest>
     */
    private function seedPickups(array $regions, array $staff, array $customers, array $types, CarbonImmutable $now): array
    {
        $pickups = [];
        foreach (range(1, 8) as $number) {
            $areaIndex = ($number - 1) % count($regions['areas']);
            $customer = $customers[($number * 3) % count($customers)];
            $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
            $date = $now->subDays(7 - $number)->toDateString();
            $status = match ($number) {
                1, 2, 3, 4 => PickupStatus::Completed,
                5, 6 => PickupStatus::Scheduled,
                7 => PickupStatus::Accepted,
                default => PickupStatus::PendingReview,
            };
            $pickup = PickupRequest::query()->firstOrCreate(
                ['request_number' => 'PUP-DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'rt_id' => $rt->id,
                    'service_area_id' => $regions['areas'][$areaIndex]->id,
                    'address' => 'Kampung Demo '.(($number % 8) + 1).', Sindangheula',
                    'selected_date' => $date,
                    'scheduled_date' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $date : null,
                    'estimated_weight_kg' => number_format(4.5 + ($number * 0.8), 3, '.', ''),
                    'notes' => 'Warga meminta penjemputan melalui dataset demo.',
                    'status' => $status,
                    'assigned_staff_id' => $staff[$areaIndex % 4]->id,
                    'accepted_at' => $status !== PickupStatus::PendingReview ? $now->subDays(7 - $number)->setTime(9, 0) : null,
                    'scheduled_at' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $now->subDays(7 - $number)->setTime(10, 0) : null,
                    'en_route_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(11, 0) : null,
                    'picked_up_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(11, 30) : null,
                    'completed_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(12, 0) : null,
                ],
            );
            foreach (array_slice($types, 0, 2) as $typeIndex => $type) {
                PickupItem::query()->firstOrCreate(
                    ['pickup_request_id' => $pickup->id, 'waste_type_id' => $type->id],
                    ['estimated_weight_kg' => number_format(2 + ($number * 0.25) + $typeIndex, 3, '.', ''), 'estimated_quantity' => 3 + $number],
                );
            }
            if (! StatusHistory::query()->where('subject_type', PickupRequest::class)->where('subject_id', $pickup->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => PickupRequest::class, 'subject_id' => $pickup->id, 'old_status' => null, 'new_status' => $status->value, 'actor_id' => $staff[$areaIndex % 4]->id, 'reason' => 'Status awal dataset demo.', 'occurred_at' => $pickup->completed_at ?? $pickup->created_at ?? $now]);
            }
            $pickups[] = $pickup;
        }

        foreach ($regions['areas'] as $area) {
            foreach (range(0, 6) as $dayOffset) {
                PickupCapacity::query()->firstOrCreate(
                    ['service_area_id' => $area->id, 'service_date' => $now->subDays($dayOffset)->toDateString()],
                    ['max_addresses' => 12, 'max_weight_kg' => '80.000', 'vehicle_label' => 'Motor Bak Demo '.($area->id), 'is_active' => true],
                );
            }
        }

        return $pickups;
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     * @param  list<WasteType>  $types
     * @param  list<WasteCondition>  $conditions
     * @param  array<int, array<int, WastePrice>>  $prices
     * @param  list<MobileService>  $mobileServices
     * @param  list<PickupRequest>  $pickups
     */
    private function seedDeposits(array $customers, array $staff, array $types, array $conditions, array $prices, array $mobileServices, array $pickups, LedgerService $ledger, CarbonImmutable $now): void
    {
        foreach (range(0, 6) as $dayOffset) {
            foreach (range(1, 8) as $sequence) {
                $demoNumber = ($dayOffset * 8) + $sequence;
                $number = 'DEP-DEMO-'.str_pad((string) $demoNumber, 3, '0', STR_PAD_LEFT);
                if (Deposit::query()->where('deposit_number', $number)->exists()) {
                    continue;
                }
                $customer = $customers[($demoNumber * 5) % count($customers)];
                $staffMember = $staff[($demoNumber - 1) % 4];
                $occurredAt = $now->subDays($dayOffset)->setTime(8 + ($sequence % 8), ($sequence * 7) % 60);
                $mobile = $dayOffset === 3 && $sequence <= 2 ? $mobileServices[1] : ($dayOffset === 0 && $sequence === 1 ? $mobileServices[2] : null);
                $pickup = $sequence === 2 && $dayOffset < 4 ? $pickups[$dayOffset] : null;
                $method = $mobile instanceof MobileService ? 'keliling' : ($pickup instanceof PickupRequest ? 'penjemputan' : 'langsung');
                $token = QrToken::generate();
                $deposit = Deposit::query()->create([
                    'deposit_number' => $number,
                    'customer_id' => $customer->id,
                    'staff_id' => $staffMember->id,
                    'method' => $method,
                    'pickup_request_id' => $pickup?->id,
                    'mobile_service_id' => $mobile?->id,
                    'location' => $mobile?->point ?? 'Loket Bank Sampah Sindangheula',
                    'occurred_at' => $occurredAt,
                    'status' => Deposit::STATUS_DRAFT,
                ]);

                $totalGrams = 0;
                $totalValue = 0;
                foreach ([$types[($demoNumber + 1) % count($types)], $types[($demoNumber + 3) % count($types)]] as $itemIndex => $type) {
                    $condition = $conditions[($demoNumber + $itemIndex) % count($conditions)];
                    $weight = number_format(1.2 + (($demoNumber + $itemIndex) % 7) * 0.65, 3, '.', '');
                    $snapshot = $prices[$type->id][$condition->id]->snapshot()->withWeight($weight);
                    $deposit->items()->create([
                        'waste_type_id' => $type->id,
                        'waste_condition_id' => $condition->id,
                        'waste_type_code' => $snapshot->wasteTypeCode,
                        'waste_type_name' => $snapshot->wasteTypeName,
                        'unit_code' => $snapshot->unitCode,
                        'unit_name' => $snapshot->unitName,
                        'unit_symbol' => $snapshot->unitSymbol,
                        'condition_code' => $snapshot->conditionCode,
                        'condition_name' => $snapshot->conditionName,
                        'weight_kg' => $snapshot->weightKg,
                        'price_per_unit' => $snapshot->pricePerUnit,
                        'subtotal' => $snapshot->subtotal,
                        'rounding_version' => $snapshot->roundingVersion,
                        'price_snapshot' => $snapshot->toArray(),
                    ]);
                    $totalGrams += Weight::fromDecimal($snapshot->weightKg)->grams();
                    $totalValue += $snapshot->subtotal;
                }
                $deposit->forceFill([
                    'status' => Deposit::STATUS_FINAL,
                    'total_weight_kg' => Weight::fromGrams($totalGrams)->decimal(),
                    'total_value' => $totalValue,
                    'finalized_at' => $occurredAt->addMinutes(20),
                    'idempotency_key' => 'demo-deposit-'.$demoNumber,
                    'verification_token_hash' => $token->hash(),
                    'verification_token_encrypted' => $token->value(),
                ])->save();
                $ledger->postDeposit($deposit, $totalValue, 'deposit:'.$deposit->id.':deposit');
                if ($pickup instanceof PickupRequest && $pickup->deposit_id === null) {
                    $pickup->forceFill(['deposit_id' => $deposit->id])->save();
                }
            }
        }
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     */
    private function seedWithdrawals(array $customers, array $staff, LedgerService $ledger, CarbonImmutable $now): void
    {
        foreach (range(1, 6) as $number) {
            $eligibleCustomers = array_values(array_filter($customers, static function (User $candidate): bool {
                return ($candidate->ledgerAccount()->first()?->availableBalance() ?? 0) >= 10_000;
            }));
            if ($eligibleCustomers === []) {
                break;
            }
            $customer = $eligibleCustomers[($number - 1) % count($eligibleCustomers)];
            $available = $customer->ledgerAccount()->firstOrFail()->availableBalance();
            $amount = min(10_000 + ($number * 2_500), intdiv($available, 2_500) * 2_500);
            if ($amount < 10_000) {
                continue;
            }
            $requestNumber = 'WDR-DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $withdrawal = WithdrawalRequest::query()->firstOrCreate(
                ['request_number' => $requestNumber],
                [
                    'customer_id' => $customer->id,
                    'requested_by_id' => $customer->id,
                    'amount' => $amount,
                    'status' => WithdrawalStatus::PendingVerification,
                    'pickup_location' => 'Loket Bank Sampah Sindangheula',
                    'pickup_date' => $now->addDays(1)->toDateString(),
                ],
            );
            if ($withdrawal->balance_hold_id === null) {
                $hold = $ledger->createHold($customer, $withdrawal, (int) $withdrawal->amount, 'withdrawal:'.$withdrawal->id.':hold');
                $withdrawal->forceFill(['balance_hold_id' => $hold->id])->save();
            }
            if (! StatusHistory::query()->where('subject_type', WithdrawalRequest::class)->where('subject_id', $withdrawal->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => null, 'new_status' => WithdrawalStatus::PendingVerification->value, 'actor_id' => $customer->id, 'reason' => 'Pengajuan demo warga.', 'occurred_at' => $now->subDays(5 - min($number, 5))]);
            }
            if ($number >= 3 && $withdrawal->status === WithdrawalStatus::PendingVerification) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::Approved, 'approver_id' => $staff[4]->id, 'approved_at' => $now->subDays(1)])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::PendingVerification->value, 'new_status' => WithdrawalStatus::Approved->value, 'actor_id' => $staff[4]->id, 'reason' => 'Disetujui untuk testing demo.', 'occurred_at' => $now->subHours(18)]);
            }
            if ($number >= 5 && $withdrawal->status === WithdrawalStatus::Approved) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::ReadyForPickup, 'payer_id' => $staff[($number - 1) % 4]->id])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::Approved->value, 'new_status' => WithdrawalStatus::ReadyForPickup->value, 'actor_id' => $staff[($number - 1) % 4]->id, 'reason' => 'Payer demo ditetapkan.', 'occurred_at' => $now->subHours(12)]);
            }
        }
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     */
    private function seedGroceries(array $customers, array $staff, LedgerService $ledger, CarbonImmutable $now): void
    {
        $packages = [
            ['code' => 'PKT-DEMO-HEMAT', 'name' => 'Paket Hemat Keluarga', 'contents' => 'Beras 5 kg, minyak 1 L, gula 1 kg', 'value' => 75_000],
            ['code' => 'PKT-DEMO-SEHAT', 'name' => 'Paket Sehat Warga', 'contents' => 'Beras 5 kg, telur 1 kg, kacang hijau 500 g', 'value' => 90_000],
        ];
        $packageModels = [];
        foreach ($packages as $packageData) {
            $packageModels[] = GroceryPackage::query()->firstOrCreate(
                ['code' => $packageData['code']],
                $packageData + ['active_from' => $now->subDays(30)->toDateString(), 'active_until' => null, 'status' => 'aktif'],
            );
        }
        foreach (range(1, 5) as $number) {
            $customer = $customers[($number * 9) % count($customers)];
            $package = $packageModels[($number - 1) % count($packageModels)];
            $redemption = GroceryRedemption::query()->firstOrCreate(
                ['request_number' => 'GRC-DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'requested_by_id' => $customer->id,
                    'grocery_package_id' => $package->id,
                    'value_snapshot' => $package->value,
                    'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
                    'source_type' => GrocerySource::FreeAid,
                    'status' => GroceryStatus::PendingVerification,
                ],
            );
            if ($redemption->source_type === GrocerySource::Balance && $redemption->balance_hold_id === null) {
                $hold = $ledger->createHold($customer, $redemption, (int) $redemption->value_snapshot, 'grocery:'.$redemption->id.':hold');
                $redemption->forceFill(['balance_hold_id' => $hold->id])->save();
            }
            if (! StatusHistory::query()->where('subject_type', GroceryRedemption::class)->where('subject_id', $redemption->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => GroceryRedemption::class, 'subject_id' => $redemption->id, 'old_status' => null, 'new_status' => GroceryStatus::PendingVerification->value, 'actor_id' => $customer->id, 'reason' => 'Penukaran demo warga.', 'occurred_at' => $now->subDays(4 - min($number, 4))]);
            }
            if ($number >= 3 && $redemption->status === GroceryStatus::PendingVerification) {
                $redemption->forceFill(['status' => GroceryStatus::Approved, 'approver_id' => $staff[4]->id, 'approved_at' => $now->subDay(), 'availability_note' => 'Stok tersedia untuk demo.', 'expires_at' => $now->addDays(7)])->save();
                StatusHistory::query()->create(['subject_type' => GroceryRedemption::class, 'subject_id' => $redemption->id, 'old_status' => GroceryStatus::PendingVerification->value, 'new_status' => GroceryStatus::Approved->value, 'actor_id' => $staff[4]->id, 'reason' => 'Disetujui untuk testing demo.', 'occurred_at' => $now->subHours(20)]);
            }
        }
    }

    /** @param list<Rt> $rts
     * @param  array{categories: list<WasteCategory>, types: list<WasteType>, conditions: list<WasteCondition>}  $master
     */
    private function seedPrograms(User $admin, array $rts, array $master, CarbonImmutable $now): void
    {
        $target = CollectionTarget::query()->firstOrCreate(
            ['target_number' => 'TGT-DEMO-MINGGU-01'],
            [
                'name' => 'Target pengumpulan minggu ini',
                'purpose' => 'Mendorong partisipasi warga selama minggu pertama operasional demo.',
                'period_start' => $now->subDays(6)->toDateString(),
                'period_end' => $now->addDays(8)->toDateString(),
                'target_weight_kg' => '250.000',
                'status' => TargetStatus::Active,
                'is_public' => true,
                'public_min_subjects' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
            ],
        );
        if (! $target->scopes()->exists()) {
            TargetScope::query()->create(['collection_target_id' => $target->id, 'waste_category_id' => $master['categories'][0]->id]);
            TargetScope::query()->create(['collection_target_id' => $target->id, 'waste_category_id' => $master['categories'][1]->id]);
        }
        $internal = CollectionTarget::query()->firstOrCreate(
            ['target_number' => 'TGT-DEMO-RT-01'],
            [
                'name' => 'Target RT demo',
                'purpose' => 'Target internal untuk menguji filter wilayah.',
                'period_start' => $now->subDays(6)->toDateString(),
                'period_end' => $now->addDays(8)->toDateString(),
                'target_weight_kg' => '80.000',
                'status' => TargetStatus::Active,
                'is_public' => false,
                'public_min_subjects' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
            ],
        );
        if (! $internal->scopes()->exists()) {
            TargetScope::query()->create(['collection_target_id' => $internal->id, 'waste_type_id' => $master['types'][0]->id, 'rt_id' => $rts[0]->id]);
        }
    }

    /** @param list<Rt> $rts */
    private function seedAnnouncements(User $admin, array $rts, CarbonImmutable $now): void
    {
        $announcement = Announcement::query()->firstOrCreate(
            ['announcement_number' => 'ANN-DEMO-MINGGU-01'],
            [
                'title' => 'Jadwal layanan bank sampah minggu ini',
                'body' => '<p>Layanan keliling hadir di beberapa titik selama minggu ini. Siapkan sampah yang sudah dipilah dan bawa kartu nasabah saat transaksi.</p>',
                'audience' => AnnouncementAudience::Public,
                'publish_start' => $now->subDays(6),
                'publish_end' => $now->addDays(7),
                'status' => AnnouncementStatus::Published,
                'priority' => 10,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now->subDays(6),
            ],
        );
        $announcement->rts()->syncWithoutDetaching(array_map(static fn (Rt $rt): int => $rt->id, array_slice($rts, 0, 6)));

        Announcement::query()->firstOrCreate(
            ['announcement_number' => 'ANN-DEMO-INTERNAL-01'],
            [
                'title' => 'Briefing petugas demo',
                'body' => '<p>Pastikan bukti transaksi dan persetujuan warga tercatat sebelum proses diselesaikan.</p>',
                'audience' => AnnouncementAudience::Internal,
                'publish_start' => $now->subDays(2),
                'publish_end' => $now->addDays(5),
                'status' => AnnouncementStatus::Published,
                'priority' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now->subDays(2),
            ],
        );
    }

    private function seedStatisticPublication(User $admin): void
    {
        StatisticPublication::query()->updateOrCreate(
            ['publication_key' => 'public-dashboard'],
            [
                'metrics' => ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'target_progress_kg', 'mobile_service_count'],
                'dimensions' => ['period'],
                'privacy_threshold' => 5,
                'is_active' => true,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );
    }
}
