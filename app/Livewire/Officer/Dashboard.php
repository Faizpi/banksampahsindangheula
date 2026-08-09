<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class Dashboard extends Component
{
    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'user.view'), 403);
    }

    public function render(PermissionChecker $permissions, GroceryService $groceries): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $today = CarbonImmutable::today('Asia/Jakarta')->toDateString();
        $pickupStatuses = [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp];
        $canViewPickups = $permissions->allows($actor, 'pickup.view');
        $canOperatePickups = $canViewPickups && $permissions->allows($actor, 'pickup.execute');
        $pickupScope = PickupRequest::query()
            ->with('customer')
            ->where('assigned_staff_id', $actor->id)
            ->whereIn('status', $pickupStatuses);
        $todayPickupQuery = (clone $pickupScope)->whereDate('scheduled_date', $today);
        $latePickupQuery = (clone $pickupScope)->whereDate('scheduled_date', '<', $today);
        $todayPickupCount = $canViewPickups ? (clone $todayPickupQuery)->count() : 0;
        $latePickupCount = $canViewPickups ? (clone $latePickupQuery)->count() : 0;
        $todayPickups = $canViewPickups ? $todayPickupQuery->orderBy('scheduled_date')->orderBy('id')->limit(8)->get() : collect();
        $latePickups = $canViewPickups ? $latePickupQuery->orderBy('scheduled_date')->orderBy('id')->limit(8)->get() : collect();
        $completedPickups = $canViewPickups ? PickupRequest::query()
            ->where('assigned_staff_id', $actor->id)
            ->where('status', PickupStatus::Completed)
            ->whereDate('completed_at', $today)
            ->count() : 0;
        $canViewDeposits = $permissions->allows($actor, 'deposit.view');
        $canResumeDeposits = $canViewDeposits && $permissions->allows($actor, 'deposit.create');
        $draftDeposits = $canViewDeposits ? Deposit::query()
            ->with('customer')
            ->where('staff_id', $actor->id)
            ->where('status', Deposit::STATUS_DRAFT)
            ->latest('occurred_at')
            ->limit(8)
            ->get() : collect();
        $canAccessGroceryTasks = $permissions->allows($actor, 'grocery.prepare') || $permissions->allows($actor, 'grocery.handover');
        $canViewGroceries = $permissions->allows($actor, 'grocery.view');
        $canHandoverGroceries = $permissions->allows($actor, 'grocery.handover');
        $groceryTasks = $canViewGroceries
            ? $groceries->visibleFor($actor)->whereIn('status', [GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])->latest()->limit(8)->get()
            : ($canHandoverGroceries ? $groceries->readyForHandover($actor)->latest()->limit(8)->get() : collect());
        $canAccessMobileServices = $permissions->allows($actor, 'mobile-service.operate');
        $canViewMobileServices = $permissions->allows($actor, 'mobile-service.view') && $canAccessMobileServices;
        $mobileServices = $canViewMobileServices ? MobileService::query()
            ->with('rt')
            ->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))
            ->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(8)
            ->get() : collect();
        $activeMobileServices = $mobileServices->where('status', MobileServiceStatus::Open)->count();
        $assignedCustomers = $todayPickups->concat($latePickups)->pluck('customer')->filter()->unique('id')->values();
        $canShowGroceryTasks = $canViewGroceries || $canHandoverGroceries;
        $metrics = [];
        if ($canViewPickups) {
            $metrics[] = ['label' => 'Pickup perlu ditangani', 'value' => $todayPickupCount + $latePickupCount, 'tone' => 'text-terracotta'];
            $metrics[] = ['label' => 'Pickup selesai hari ini', 'value' => $completedPickups, 'tone' => 'text-forest-600'];
            $metrics[] = ['label' => 'Nasabah dalam tugas', 'value' => $assignedCustomers->count(), 'tone' => 'text-forest-600'];
        }
        if ($canViewDeposits) {
            $metrics[] = ['label' => 'Draf setoran', 'value' => $draftDeposits->count(), 'tone' => 'text-harvest-gold'];
        }
        if ($canShowGroceryTasks) {
            $metrics[] = ['label' => 'Tugas sembako', 'value' => $groceryTasks->count(), 'tone' => 'text-harvest-gold'];
        }
        if ($canViewMobileServices) {
            $metrics[] = ['label' => 'Layanan keliling aktif', 'value' => $activeMobileServices.' aktif', 'tone' => 'text-sky-blue'];
        }

        return view('livewire.officer.dashboard', [
            'canIdentifyCustomers' => $permissions->allows($actor, 'customer.view'),
            'canViewProfile' => $permissions->allows($actor, 'profile.view'),
            'canViewPickups' => $canViewPickups,
            'canOperatePickups' => $canOperatePickups,
            'statisticsHref' => route('statistics.internal'),
            'canViewStatistics' => $permissions->allows($actor, 'statistics.internal.view'),
            'canViewDeposits' => $canViewDeposits,
            'canResumeDeposits' => $canResumeDeposits,
            'canAccessGroceryTasks' => $canAccessGroceryTasks,
            'canShowGroceryTasks' => $canShowGroceryTasks,
            'canAccessMobileServices' => $canAccessMobileServices,
            'canViewMobileServices' => $canViewMobileServices,
            'identificationHref' => route('officer.customer-identification'),
            'groceryTasksHref' => route('officer.grocery.tasks'),
            'todayPickups' => $todayPickups,
            'latePickups' => $latePickups,
            'draftDeposits' => $draftDeposits,
            'groceryTasks' => $groceryTasks,
            'mobileServices' => $mobileServices,
            'assignedCustomers' => $assignedCustomers,
            'metrics' => $metrics,
        ]);
    }
}
