<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class MobileServiceTasks extends Component
{
    public function open(int $serviceId, MobileServiceService $services): void
    {
        $actor = $this->actor();
        $service = $this->assignedService($actor, $serviceId);
        $services->transition($actor, $service, MobileServiceStatus::Open);
        session()->flash('success', 'Layanan keliling dibuka.');
    }

    public function close(int $serviceId, MobileServiceService $services): void
    {
        $actor = $this->actor();
        $service = $this->assignedService($actor, $serviceId);
        $services->transition($actor, $service, MobileServiceStatus::Closed);
        session()->flash('success', 'Layanan keliling ditutup.');
    }

    public function recap(int $serviceId, MobileServiceService $services): void
    {
        $actor = $this->actor();
        $service = $this->assignedService($actor, $serviceId);
        session()->flash('mobile-recap-'.$serviceId, $services->recap($actor, $service));
    }

    public function render(): View
    {
        $actor = $this->actor();

        return view('livewire.officer.mobile-service-tasks', ['services' => MobileService::query()->with(['rt', 'staff'])->whereHas('staff', fn ($query) => $query->whereKey($actor->id))->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])->orderBy('starts_at')->get()]);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function assignedService(User $actor, int $serviceId): MobileService
    {
        $service = MobileService::query()
            ->whereKey($serviceId)
            ->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))
            ->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])
            ->first();

        abort_unless($service instanceof MobileService, 404);

        return $service;
    }
}
