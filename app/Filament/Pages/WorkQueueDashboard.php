<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class WorkQueueDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pusat kerja';

    protected static ?string $title = 'Pusat kerja';

    protected string $view = 'filament.backoffice.work-queue-dashboard';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'backoffice.access');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $today = today('Asia/Jakarta');
        $expiryHorizon = now('Asia/Jakarta')->addDays(2);

        $queues = [
            [
                'label' => 'Verifikasi warga',
                'count' => User::query()->where('status', UserStatus::PendingVerification)->whereHas('customerProfile')->count(),
                'description' => 'Akun baru menunggu keputusan.',
                'href' => CitizenVerificationResource::getUrl('index'),
            ],
            [
                'label' => 'Pickup hari ini',
                'count' => PickupRequest::query()->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])->where(function ($query) use ($today): void {
                    $query->whereDate('scheduled_date', $today)->orWhereDate('selected_date', $today);
                })->count(),
                'description' => 'Tugas yang perlu dipantau hari ini.',
                'href' => PickupRequestResource::getUrl('index'),
            ],
            [
                'label' => 'Pencairan menunggu keputusan',
                'count' => WithdrawalRequest::query()->where('status', WithdrawalStatus::PendingVerification)->count(),
                'description' => 'Review consent, saldo hold, dan dampak approve.',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ],
            [
                'label' => 'Segera kedaluwarsa',
                'count' => WithdrawalRequest::query()->whereIn('status', [WithdrawalStatus::Approved, WithdrawalStatus::ReadyForPickup])->whereBetween('expires_at', [now('Asia/Jakarta'), $expiryHorizon])->count(),
                'description' => 'Pencairan yang perlu dikonfirmasi sebelum batas.',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ],
        ];

        return [
            'queues' => $queues,
            'environment' => app()->environment(),
            'maintenanceEnabled' => app()->maintenanceMode()->active(),
            'lastUpdated' => now('Asia/Jakarta')->translatedFormat('d F Y, H:i'),
            'activeAreas' => ServiceArea::query()->where('is_active', true)->count(),
        ];
    }
}
