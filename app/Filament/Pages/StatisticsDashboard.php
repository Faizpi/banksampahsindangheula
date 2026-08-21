<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class StatisticsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Statistik internal';

    protected static ?string $title = 'Statistik internal';

    protected string $view = 'filament.backoffice.statistics-dashboard';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'statistics.internal.view');
    }
}
