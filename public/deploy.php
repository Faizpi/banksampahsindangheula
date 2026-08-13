<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;

/*
 * Protected release console for this Laravel application.
 *
 * Enable it only when DEPLOY_CONSOLE_TOKEN is set in the private production
 * environment. The token must never be committed or placed in this file.
 */

const DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH = 12000;

$projectPath = realpath(__DIR__.'/../bank-sampah');

// Shared hosting may lock the primary document root to /public_html. In that
// layout this console is copied to /public_html, while the Laravel source is
// kept privately in the sibling /bank-sampah directory. Local and standard
// Laravel layouts continue to use the conventional parent path.
if ($projectPath === false || ! is_file($projectPath.'/artisan')) {
    $projectPath = realpath(__DIR__.'/..');
}

if ($projectPath === false || ! is_file($projectPath.'/artisan')) {
    http_response_code(500);
    exit('Project root could not be resolved.');
}

if (! is_file($projectPath.'/vendor/autoload.php')) {
    http_response_code(503);
    exit('Dependencies are not installed. Run composer install through SSH first.');
}

chdir($projectPath);

require $projectPath.'/vendor/autoload.php';

// Config cache makes Laravel skip loading .env. Load it here so this protection
// remains available before a cache-rebuild deployment action can run.
Dotenv::createImmutable($projectPath)->safeLoad();

function deployConsoleEnvironment(string $key): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) ? trim($value) : '';
}

/** @var Application $app */
$app = require $projectPath.'/bootstrap/app.php';
$configuredToken = deployConsoleEnvironment('DEPLOY_CONSOLE_TOKEN');

if ($configuredToken === '') {
    http_response_code(503);
    exit('Deploy console is disabled. Configure DEPLOY_CONSOLE_TOKEN in the private environment to enable it.');
}

$allowedIps = array_values(array_filter(array_map(
    static fn (string $ip): string => trim($ip),
    explode(',', deployConsoleEnvironment('DEPLOY_CONSOLE_ALLOWED_IPS')),
)));

if ($allowedIps !== [] && ! in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $allowedIps, true)) {
    http_response_code(403);
    exit('This IP address is not authorised to use the deploy console.');
}

$actions = [
    'status' => [
        'label' => 'Periksa status migrasi',
        'description' => 'Menampilkan status migrasi tanpa mengubah aplikasi.',
        'commands' => [
            ['migrate:status', []],
        ],
    ],
    'release' => [
        'label' => 'Selesaikan deployment',
        'description' => 'Membersihkan cache, menjalankan migrasi produksi, lalu membangun cache aplikasi.',
        'commands' => [
            ['optimize:clear', []],
            ['migrate', ['--force' => true]],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'rebuild-cache' => [
        'label' => 'Bangun ulang cache',
        'description' => 'Gunakan setelah perubahan konfigurasi, route, atau view tanpa migrasi database.',
        'commands' => [
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'view-logs' => [
        'label' => 'Lihat log aplikasi terbaru',
        'description' => 'Menampilkan 200 baris terakhir dari storage/logs/laravel.log tanpa mengubahnya.',
        'commands' => [],
    ],
];

$results = [];
$message = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string) ($_POST['token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if (! hash_equals($configuredToken, $submittedToken)) {
        http_response_code(403);
        $message = ['error', 'Token deploy tidak valid.'];
    } elseif (! isset($actions[$action])) {
        http_response_code(422);
        $message = ['error', 'Aksi deployment tidak dikenal.'];
    } elseif ($action === 'view-logs') {
        $logFile = $projectPath.'/storage/logs/laravel.log';
        $results[] = [
            'command' => 'storage/logs/laravel.log',
            'success' => true,
            'output' => latestDeployLogLines($logFile),
            'duration' => 0.0,
        ];
        $message = ['success', 'Log aplikasi terbaru berhasil dimuat.'];
    } else {
        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);

        foreach ($actions[$action]['commands'] as [$command, $parameters]) {
            $startedAt = microtime(true);

            try {
                $exitCode = $kernel->call($command, $parameters);
                $output = $kernel->output();
                $results[] = [
                    'command' => $command,
                    'success' => $exitCode === 0,
                    'output' => $output === '' ? '(Tidak ada output.)' : $output,
                    'duration' => microtime(true) - $startedAt,
                ];

                if ($exitCode !== 0) {
                    break;
                }
            } catch (Throwable $exception) {
                report($exception);
                $results[] = [
                    'command' => $command,
                    'success' => false,
                    'output' => 'Command gagal dijalankan. Periksa log aplikasi melalui kanal operasional yang aman.',
                    'duration' => microtime(true) - $startedAt,
                ];
                break;
            }
        }

        $hasFailure = array_filter($results, static fn (array $result): bool => ! $result['success']) !== [];
        $message = $hasFailure
            ? ['error', 'Deployment berhenti karena ada command yang gagal.']
            : ['success', 'Aksi deployment selesai dijalankan.'];
    }
}

function escapeDeployConsole(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function compactDeployOutput(string $output): string
{
    if (mb_strlen($output) <= DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH) {
        return $output;
    }

    return "[Output lama dipotong demi keamanan.]\n\n".mb_substr($output, -DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH);
}

function latestDeployLogLines(string $logFile, int $lineLimit = 200): string
{
    if (! is_file($logFile) || ! is_readable($logFile)) {
        return 'Log aplikasi belum tersedia atau tidak dapat dibaca.';
    }

    $fileSize = filesize($logFile);

    if ($fileSize === false || $fileSize === 0) {
        return '(Log aplikasi kosong.)';
    }

    $bytesToRead = min($fileSize, 256 * 1024);
    $handle = fopen($logFile, 'rb');

    if ($handle === false) {
        return 'Log aplikasi tidak dapat dibuka.';
    }

    try {
        fseek($handle, -$bytesToRead, SEEK_END);
        $content = stream_get_contents($handle);
    } finally {
        fclose($handle);
    }

    if (! is_string($content)) {
        return 'Log aplikasi tidak dapat dibaca.';
    }

    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

    if ($fileSize > $bytesToRead) {
        array_shift($lines);
    }

    $latestLines = array_slice($lines, -$lineLimit);

    return implode("\n", $latestLines) ?: '(Log aplikasi kosong.)';
}

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deploy Console | Bank Sampah Sindangheula</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { max-width: 780px; margin: 0 auto; padding: 2rem 1rem 4rem; color: #18251c; background: #f5f7f2; }
        main { padding: 1.5rem; border: 1px solid #d5ddd1; border-radius: 1rem; background: #fff; }
        h1 { margin: 0; font-size: 1.5rem; } p { line-height: 1.55; }
        .notice, .result { margin-top: 1rem; padding: 1rem; border-radius: .75rem; }
        .success { color: #135b31; background: #e7f6eb; } .error { color: #8a1d1d; background: #fceaea; }
        form { margin-top: 1rem; padding: 1rem; border: 1px solid #d5ddd1; border-radius: .75rem; }
        label, input, select, button { display: block; width: 100%; } label { font-weight: 700; }
        input, select, button { box-sizing: border-box; margin-top: .45rem; min-height: 2.7rem; padding: .55rem .7rem; border-radius: .45rem; }
        input, select { border: 1px solid #aab8a4; background: #fff; } button { border: 0; color: #fff; background: #24663d; font-weight: 700; cursor: pointer; }
        button:hover { background: #174d2d; } small { color: #56635b; } pre { overflow: auto; margin: .7rem 0 0; padding: 1rem; color: #d8eadb; background: #122217; border-radius: .5rem; white-space: pre-wrap; }
    </style>
</head>
<body>
<main>
    <h1>Deploy Console</h1>
    <p>Console terbatas untuk <strong>Bank Sampah Sindangheula</strong>. Hanya gunakan setelah backup pra-deploy dan aset Vite sudah tersedia. Log tersedia untuk pembacaan saja.</p>

    <?php if ($message !== null) { ?>
        <div class="notice <?= escapeDeployConsole($message[0]) ?>"><?= escapeDeployConsole($message[1]) ?></div>
    <?php } ?>

    <form method="post" autocomplete="off">
        <label for="token">Token deploy</label>
        <input id="token" name="token" type="password" required autocomplete="current-password">

        <label for="action" style="margin-top: 1rem;">Aksi</label>
        <select id="action" name="action" required>
            <?php foreach ($actions as $key => $action) { ?>
                <option value="<?= escapeDeployConsole($key) ?>"><?= escapeDeployConsole($action['label']) ?></option>
            <?php } ?>
        </select>
        <small>Pilih aksi dengan hati-hati. Detail setiap aksi tersedia di panduan deployment proyek.</small>

        <button type="submit" style="margin-top: 1rem;">Jalankan aksi</button>
    </form>

    <?php foreach ($results as $result) { ?>
        <section class="result <?= $result['success'] ? 'success' : 'error' ?>">
            <strong><?= escapeDeployConsole($result['command']) ?> — <?= $result['success'] ? 'berhasil' : 'gagal' ?></strong>
            <small><?= number_format($result['duration'], 2) ?> detik</small>
            <pre><?= escapeDeployConsole(compactDeployOutput($result['output'])) ?></pre>
        </section>
    <?php } ?>
</main>
</body>
</html>
