<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
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
        $now = now('Asia/Jakarta');
        $expiryHorizon = $now->copy()->addDays(2);
        $permissions = app(PermissionChecker::class);
        $actor = $this->actor();
        $queues = [];

        if ($permissions->allows($actor, 'user.verify')) {
            $queues[] = [
                'label' => 'Verifikasi warga',
                'count' => User::query()->where('status', UserStatus::PendingVerification)->whereHas('customerProfile')->count(),
                'description' => 'Periksa identitas dan aktifkan akun.',
                'cta' => 'Periksa warga',
                'href' => CitizenVerificationResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'pickup.view')) {
            $queues[] = [
                'label' => 'Pickup hari ini',
                'count' => PickupRequest::query()->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])->where(function ($query) use ($today): void {
                    $query->whereDate('scheduled_date', $today)->orWhereDate('selected_date', $today);
                })->count(),
                'description' => 'Pantau penugasan dan keterlambatan.',
                'cta' => 'Pantau pickup',
                'href' => PickupRequestResource::getUrl('index'),
            ];
            $queues[] = [
                'label' => 'Pickup terlambat',
                'count' => PickupRequest::query()->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])->where(function ($query) use ($today): void {
                    $query->whereDate('scheduled_date', '<', $today)->orWhere(function ($query) use ($today): void {
                        $query->whereNull('scheduled_date')->whereDate('selected_date', '<', $today);
                    });
                })->count(),
                'description' => 'Tindak lanjuti tugas yang melewati jadwal.',
                'cta' => 'Tinjau pickup',
                'href' => PickupRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'withdrawal.approve')) {
            $queues[] = [
                'label' => 'Pencairan menunggu keputusan',
                'count' => WithdrawalRequest::query()->where('status', WithdrawalStatus::PendingVerification)->count(),
                'description' => 'Periksa bukti dan dana yang ditahan.',
                'cta' => 'Tinjau pencairan',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ];
            $queues[] = [
                'label' => 'Pencairan belum ditugaskan',
                'count' => WithdrawalRequest::query()->where('status', WithdrawalStatus::Approved)->whereNull('payer_id')->count(),
                'description' => 'Tetapkan petugas pembayaran untuk pencairan.',
                'cta' => 'Tetapkan petugas',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'withdrawal.view')) {
            $queues[] = [
                'label' => 'Segera kedaluwarsa',
                'count' => WithdrawalRequest::query()->whereIn('status', [WithdrawalStatus::Approved, WithdrawalStatus::ReadyForPickup])->whereBetween('expires_at', [$now, $expiryHorizon])->count(),
                'description' => 'Selesaikan dalam 2 hari.',
                'cta' => 'Lihat batas waktu',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'grocery.approve')) {
            $queues[] = [
                'label' => 'Penukaran sembako',
                'count' => GroceryRedemption::query()->where('status', GroceryStatus::PendingVerification)->count(),
                'description' => 'Periksa ketersediaan dan setujui pengajuan.',
                'cta' => 'Tinjau sembako',
                'href' => GroceryRedemptionResource::getUrl('index'),
            ];
        }

        return [
            'queues' => $queues,
            'hasVisibleQueues' => $queues !== [],
            'environment' => app()->environment(),
            'maintenanceEnabled' => app()->maintenanceMode()->active(),
            'lastUpdated' => now('Asia/Jakarta')->translatedFormat('d F Y, H:i'),
            'activeAreas' => ServiceArea::query()->where('is_active', true)->count(),
        ];
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
