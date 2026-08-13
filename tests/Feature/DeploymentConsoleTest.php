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
