<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource;
use App\Filament\Resources\CustomersRegions\Models\Rts\RtResource;
use App\Filament\Resources\CustomersRegions\Models\Rws\RwResource;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource;
use App\Filament\Resources\Deposits\Models\Deposits\DepositResource;
use App\Filament\Resources\Groceries\Models\GroceryPackages\GroceryPackageResource;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Filament\Resources\Identity\Models\PasswordAssistances\PasswordAssistanceResource;
use App\Filament\Resources\Identity\Models\Permissions\PermissionResource;
use App\Filament\Resources\Identity\Models\Roles\RoleResource;
use App\Filament\Resources\Identity\Models\SessionInventories\SessionInventoryResource;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource;
use App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\MobileServices\Models\MobileServices\MobileServiceResource;
use App\Filament\Resources\Pickups\Models\PickupCapacities\PickupCapacityResource;
use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Filament\Resources\Statistics\Models\StatisticPublications\StatisticPublicationResource;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource;
use App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource;
use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackofficePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_superadmin_with_seeded_backoffice_access_can_access_the_technical_panel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $superadmin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($admin->fresh()->canAccessPanel($panel));
        self::assertTrue($superadmin->fresh()->canAccessPanel($panel));
        self::assertFalse(User::factory()->create()->canAccessPanel($panel));

        $this->get('/backoffice')->assertRedirect('/backoffice/login');
        $this->actingAs($admin->fresh())->get('/backoffice/login')->assertRedirect('/backoffice');
        self::assertNotContains(
            'Kontrol teknis',
            $this->navigationLabelsForGroup($panel, 'Sistem & Teknis'),
        );

        $this->actingAs($superadmin->fresh())->get('/backoffice/login')->assertRedirect('/backoffice');
        self::assertContains(
            'Kontrol teknis',
            $this->navigationLabelsForGroup($panel, 'Sistem & Teknis'),
        );
    }

    public function test_technical_dashboard_is_discovered_and_permission_gated(): void
    {
        self::assertContains(OperationsDashboard::class, array_values(Filament::getPanel('backoffice')->getPages()));

        $technical = User::factory()->create();
        $viewer = User::factory()->create();
        $this->grant($technical, 'technical-dashboard', 'system.maintenance');
        $this->grant($viewer, 'viewer-dashboard', 'backoffice.access');

        $this->actingAs($technical->fresh());
        self::assertTrue(OperationsDashboard::canAccess());
        $this->actingAs($viewer->fresh());
        self::assertFalse(OperationsDashboard::canAccess());
    }

    public function test_backoffice_navigation_has_the_settled_taxonomy_groups_in_order(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame(
            [
                'Identitas & Akses',
                'Transaksi & Saldo',
                'Operasional Lapangan',
                'Program & Publikasi',
                'Laporan & Audit',
                'Data Master',
                'Sistem & Teknis',
            ],
            collect($panel->getNavigationGroups())->map(static fn ($group): string => $group->getLabel())->all(),
        );
    }

    public function test_admin_navigation_uses_the_stable_resource_order_for_each_group(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
        $panel = Filament::getPanel('backoffice');

        $this->actingAs($admin->fresh());

        self::assertSame(
            ['Nasabah', 'Verifikasi Warga', 'Pengguna', 'Bantuan Kata Sandi', 'Sesi Pengguna', 'Role', 'Permission'],
            $this->navigationLabelsForGroup($panel, 'Identitas & Akses'),
        );
        self::assertSame(
            ['Setoran dan Koreksi', 'Ledger', 'Hold Saldo', 'Pencairan', 'Penukaran Sembako'],
            $this->navigationLabelsForGroup($panel, 'Transaksi & Saldo'),
        );
        self::assertSame(
            ['Penjemputan', 'Kapasitas Penjemputan'],
            $this->navigationLabelsForGroup($panel, 'Operasional Lapangan'),
        );
        self::assertSame(
            ['Pengumuman', 'Target Pengumpulan', 'Layanan Keliling', 'Statistik Publik'],
            $this->navigationLabelsForGroup($panel, 'Program & Publikasi'),
        );
        self::assertSame(
            ['Audit log'],
            $this->navigationLabelsForGroup($panel, 'Laporan & Audit'),
        );
        self::assertSame(
            ['Area Pelayanan', 'Dusun', 'RW', 'RT', 'Paket Sembako', 'Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Harga Sampah', 'Satuan Sampah'],
            $this->navigationLabelsForGroup($panel, 'Data Master'),
        );
        self::assertSame([], $this->navigationLabelsForGroup($panel, 'Sistem & Teknis'));
    }

    public function test_backoffice_panel_discovers_only_the_regional_resources(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame([app_path('Filament/Resources')], $panel->getResourceDirectories());
        self::assertSame(['App\\Filament\\Resources'], $panel->getResourceNamespaces());
        self::assertEqualsCanonicalizing([
            AnnouncementResource::class,
            CitizenVerificationResource::class,
            CustomerResource::class,
            UserResource::class,
            PasswordAssistanceResource::class,
            SessionInventoryResource::class,
            PermissionResource::class,
            RoleResource::class,
            MobileServiceResource::class,
            PickupRequestResource::class,
            PickupCapacityResource::class,
            CollectionTargetResource::class,
            DusunResource::class,
            RwResource::class,
            RtResource::class,
            ServiceAreaResource::class,
            WasteCategoryResource::class,
            WasteConditionResource::class,
            WasteTypeResource::class,
            WasteUnitResource::class,
            WastePriceResource::class,
            WithdrawalRequestResource::class,
            GroceryPackageResource::class,
            StatisticPublicationResource::class,
            GroceryRedemptionResource::class,
            AuditLogResource::class,
            DepositResource::class,
            LedgerEntryResource::class,
            BalanceHoldResource::class,
        ], array_values($panel->getResources()));
    }

    public function test_only_authorized_panel_users_see_regional_resources_under_data_master(): void
    {
        $regionalManager = User::factory()->create();
        $panelUserWithoutRegionalPermission = User::factory()->create();
        $this->grant($regionalManager, 'regional-admin', 'backoffice.access', 'region.manage');
        $this->grant($panelUserWithoutRegionalPermission, 'backoffice-user', 'backoffice.access');

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($regionalManager->fresh()->canAccessPanel($panel));
        $this->actingAs($regionalManager->fresh());
        self::assertSame(['Area Pelayanan', 'Dusun', 'RT', 'RW'], $this->dataMasterNavigationLabels($panel));

        self::assertTrue($panelUserWithoutRegionalPermission->fresh()->canAccessPanel($panel));
        $this->actingAs($panelUserWithoutRegionalPermission->fresh());
        self::assertSame([], $this->dataMasterNavigationLabels($panel));
    }

    public function test_only_waste_viewers_and_managers_see_waste_resources_with_mutation_reserved_for_managers(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'waste-viewer', 'backoffice.access', 'waste.view');
        $this->grant($manager, 'waste-manager', 'backoffice.access', 'waste.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertSame(['Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WasteCategory::class));

        $this->actingAs($manager->fresh());
        self::assertSame(['Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WasteCategory::class));
    }

    public function test_price_navigation_requires_price_view_and_creation_requires_price_manage(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'price-viewer', 'backoffice.access', 'price.view');
        $this->grant($manager, 'price-manager', 'backoffice.access', 'price.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WastePrice::class));

        $this->actingAs($manager->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WastePrice::class));
    }

    /** @return list<string> */
    private function dataMasterNavigationLabels(Panel $panel): array
    {
        return $this->navigationLabelsForGroup($panel, 'Data Master', sortLabels: true);
    }

    /** @return list<string> */
    private function navigationLabelsForGroup(Panel $panel, string $label, bool $sortLabels = false): array
    {
        app()->forgetInstance(NavigationManager::class);

        foreach ($panel->getNavigation() as $group) {
            if ($group->getLabel() !== $label) {
                continue;
            }

            $labels = $group->getItems()->map(static fn ($item): string => $item->getLabel())->all();

            if ($sortLabels) {
                sort($labels);
            }

            return $labels;
        }

        return [];
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => "Test role {$roleName}"],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);
    }
}
