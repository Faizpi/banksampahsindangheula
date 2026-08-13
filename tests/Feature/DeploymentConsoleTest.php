<?php

declare(strict_types=1);

it('runs database migration before clearing the database-backed cache during a release', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    preg_match("/'release'\s*=>\s*\[.*?'commands'\s*=>\s*\[(.*?)\],\s*\],/s", (string) $console, $matches);

    expect($matches)->toHaveKey(1)
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['optimize:clear', []]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->toBeLessThan(strpos($matches[1], "['optimize:clear', []]"));
});

it('provides a standalone ordinary migration action that preserves existing data', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    $start = strpos((string) $console, "'migrate' => [");
    $end = strpos((string) $console, "'release' => [");
    $block = $start === false || $end === false ? '' : substr((string) $console, $start, $end - $start);

    expect($block)->not->toBe('')
        ->and($console)->toContain('Jalankan migrasi biasa')
        ->and($console)->toContain('tanpa menghapus tabel atau data')
        ->and($block)->toContain("['migrate', ['--force' => true]]")
        ->and($block)->not->toContain('migrate:fresh')
        ->and($block)->not->toContain('db:seed');
});

it('provides an explicit confirmed fresh deployment action for a new database', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("'fresh-release'")
        ->and($console)->toContain("['migrate:fresh', ['--seed' => true, '--force' => true]]")
        ->and($console)->toContain("'RESET DATABASE'")
        ->and($console)->toContain('DEPLOY_CONSOLE_TOKEN');
});

it('provides a confirmed data seed action for temporary production testing', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("'seed-demo-data'")
        ->and($console)->toContain("'SEED DATA UJI'")
        ->and($console)->toContain('Database\\\\Seeders\\\\LocalDataSeeder');
});

it('migrates before seeding temporary demo data', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    preg_match("/'seed-demo-data'\s*=>\s*\[.*?'commands'\s*=>\s*\[(.*?)\],\s*\],/s", (string) $console, $matches);

    expect($matches)->toHaveKey(1)
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['db:seed', ['--class' => 'Database\\\\Seeders\\\\LocalDataSeeder', '--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->toBeLessThan(strpos($matches[1], "['db:seed', ['--class' => 'Database\\\\Seeders\\\\LocalDataSeeder', '--force' => true]]"));
});

it('shows a bounded exception summary to the authenticated deployment operator', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain('deployConsoleFailureMessage')
        ->and($console)->toContain("preg_replace('/\\s*\\(SQL:.*\$/i'")
        ->and($console)->toContain('Periksa log aplikasi melalui aksi Lihat log aplikasi');
});
