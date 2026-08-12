<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Reconciliation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Rekonsiliasi';

    protected static ?string $title = 'Rekonsiliasi';

    protected string $view = 'filament.backoffice.reconciliation';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return false;
        }

        $permissions = app(PermissionChecker::class);

        return $permissions->allows($actor, 'transaction.correct')
            || $permissions->allows($actor, 'transaction.reverse')
            || $permissions->allows($actor, 'ledger.adjust');
    }

    /** @return array<string, bool> */
    protected function getViewData(): array
    {
        $actor = auth()->user();
        $permissions = app(PermissionChecker::class);

        return [
            'canReviewDeposits' => $actor instanceof User && $permissions->allows($actor, 'deposit.view'),
            'canViewLedger' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewHolds' => $actor instanceof User && $permissions->allows($actor, 'ledger.view'),
            'canViewAudit' => $actor instanceof User && $permissions->allows($actor, 'audit.view'),
        ];
    }
}
