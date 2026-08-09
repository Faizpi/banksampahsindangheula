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
        $canOperatePickups = $permissions->allows($actor, 'pickup.view');
        $pickupScope = PickupRequest::query()
            ->with('customer')
            ->where('assigned_staff_id', $actor->id)
            ->whereIn('status', $pickupStatuses);
        $todayPickupQuery = (clone $pickupScope)->whereDate('scheduled_date', $today);
        $latePickupQuery = (clone $pickupScope)->whereDate('scheduled_date', '<', $today);
        $todayPickupCount = $canOperatePickups ? (clone $todayPickupQuery)->count() : 0;
        $latePickupCount = $canOperatePickups ? (clone $latePickupQuery)->count() : 0;
        $todayPickups = $canOperatePickups ? $todayPickupQuery->orderBy('scheduled_date')->orderBy('id')->limit(8)->get() : collect();
        $latePickups = $canOperatePickups ? $latePickupQuery->orderBy('scheduled_date')->orderBy('id')->limit(8)->get() : collect();
        $completedPickups = $canOperatePickups ? PickupRequest::query()
            ->where('assigned_staff_id', $actor->id)
            ->where('status', PickupStatus::Completed)
            ->whereDate('completed_at', $today)
            ->count() : 0;
        $canViewDeposits = $permissions->allows($actor, 'deposit.view');
        $draftDeposits = $canViewDeposits ? Deposit::query()
            ->with('customer')
            ->where('staff_id', $actor->id)
            ->where('status', Deposit::STATUS_DRAFT)
            ->latest('occurred_at')
            ->limit(8)
            ->get() : collect();
        $groceryTasks = $permissions->allows($actor, 'grocery.view')
            ? $groceries->visibleFor($actor)->whereIn('status', [GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])->latest()->limit(8)->get()
            : collect();
        $canViewMobileServices = $permissions->allows($actor, 'mobile-service.view') && $permissions->allows($actor, 'mobile-service.operate');
        $mobileServices = $canViewMobileServices ? MobileService::query()
            ->with('rt')
            ->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))
            ->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])
            ->orderBy('starts_at')
            ->limit(8)
            ->get() : collect();
        $activeMobileServices = $mobileServices->where('status', MobileServiceStatus::Open)->count();
        $assignedCustomers = $todayPickups->concat($latePickups)->pluck('customer')->filter()->unique('id')->values();

        return view('livewire.officer.dashboard', [
            'identificationHref' => route('officer.customer-identification'),
            'groceryTasksHref' => route('officer.grocery.tasks'),
            'todayPickups' => $todayPickups,
            'latePickups' => $latePickups,
            'draftDeposits' => $draftDeposits,
            'groceryTasks' => $groceryTasks,
            'mobileServices' => $mobileServices,
            'assignedCustomers' => $assignedCustomers,
            'metrics' => [
                'pending_pickups' => $todayPickupCount + $latePickupCount,
                'completed_pickups' => $completedPickups,
                'draft_deposits' => $draftDeposits->count(),
                'grocery_tasks' => $groceryTasks->count(),
                'active_mobile_services' => $activeMobileServices,
                'assigned_customers' => $assignedCustomers->count(),
            ],
        ]);
    }
}
