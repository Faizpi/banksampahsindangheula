<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Authorization\PermissionChecker;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class Dashboard extends Component
{
    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'withdrawal.view'), 403);
    }

    public function render(WithdrawalService $withdrawals): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        $canPay = app(PermissionChecker::class)->allows($actor, 'withdrawal.pay');
        $readyPayments = $canPay ? $withdrawals->payableFor($actor)->latest()->limit(8)->get() : collect();

        return view('livewire.treasurer.dashboard', [
            'canViewStatistics' => app(PermissionChecker::class)->allows($actor, 'statistics.internal.view'),
            'readyPayments' => $readyPayments,
            'readyPaymentTotal' => (int) $readyPayments->sum('amount'),
        ]);
    }
}
