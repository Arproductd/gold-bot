<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0077);

function get_hosted_rates_source(string $url): string
{
    $request = curl_init($url);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Gold-Bot-Hosted-Rates',
    ]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    if ($body === false || $status !== 200) {
        throw new RuntimeException("GitHub source request failed with HTTP $status.");
    }
    return $body;
}

function cached_hosted_release(string $root): ?string
{
    $current_file = $root . '/.rates-revision';
    $current = is_file($current_file) ? trim(file_get_contents($current_file)) : '';
    $release = $root . '/releases/' . $current;

    return preg_match('/\A[a-f0-9]{40}\z/', $current) && is_file($release . '/.ready') ? $release : null;
}

function includes_market_bot(string $release): bool
{
    return is_file($release . '/main.php') && is_file($release . '/storage.php');
}

function sync_hosted_release(string $root): string
{
    $current_file = $root . '/.rates-revision';
    $current = cached_hosted_release($root);
    $latest = json_decode(get_hosted_rates_source(
        'https://api.github.com/repos/Arproductd/gold-bot/git/ref/heads/main'
    ), true);
    $revision = $latest['object']['sha'] ?? '';
    if (!preg_match('/\A[a-f0-9]{40}\z/', $revision)) {
        throw new RuntimeException('GitHub did not return a valid main revision.');
    }

    $release = $root . '/releases/' . $revision;
    if (!is_file($release . '/.ready') || !includes_market_bot($release)) {
        if (!is_dir($release) && !mkdir($release, 0700, true)) {
            throw new RuntimeException('Could not create the hosted rates release directory.');
        }
        foreach (['config.php', 'jalali.php', 'prices.php', 'notifier.php', 'usd-cny.php', 'storage.php', 'main.php'] as $name) {
            $source = get_hosted_rates_source("https://raw.githubusercontent.com/Arproductd/gold-bot/$revision/PHP/$name");
            $target = $release . '/' . $name;
            $pending = $target . '.next';
            if (strpos($source, '<?php') !== 0 || file_put_contents($pending, $source) !== strlen($source) || !rename($pending, $target)) {
                throw new RuntimeException("Could not install $name for the hosted rates release.");
            }
        }
        if (file_put_contents($release . '/.ready', $revision) !== strlen($revision)) {
            throw new RuntimeException('Could not mark the hosted rates release ready.');
        }
    }

    if ($current !== $release) {
        $next = $current_file . '.next';
        if (file_put_contents($next, $revision) !== strlen($revision) || !rename($next, $current_file)) {
            throw new RuntimeException('Could not activate the downloaded rates release.');
        }
        echo 'Activated GitHub revision ', substr($revision, 0, 12), PHP_EOL;
    }
    return $release;
}

$root = __DIR__;
$log = $root . '/cron.log';
ini_set('error_log', $log);

if (($argv[1] ?? '') === '--market') {
    $env_file = getenv('GOLD_BOT_ENV_FILE');
    if (!$env_file || !is_readable($env_file)) {
        error_log('Configure GOLD_BOT_ENV_FILE with the existing market bot environment.');
        exit(1);
    }

    $state_dir = dirname($env_file);
    putenv('GOLD_BOT_STATE_DIR=' . $state_dir);
    $release = cached_hosted_release($root);
    if ($release === null || !includes_market_bot($release)) {
        $release = $state_dir;
        error_log('Market cron using the local checkout while GitHub sync prepares the release.');
    } else {
        error_log('Market cron using GitHub revision ' . substr(basename($release), 0, 12) . '.');
    }
    require $release . '/main.php';
    return;
}

$lock = fopen($root . '/.rates-sync.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    error_log('Could not lock the hosted rates deployment.');
    exit(1);
}

try {
    $release = sync_hosted_release($root);
} catch (Throwable $error) {
    error_log($error->getMessage());
    $release = cached_hosted_release($root) ?? $root . '/PHP';
} finally {
    fclose($lock);
}

putenv('USD_CNY_STATE_FILE=' . $root . '/PHP/.usd-cny-last-slot');
putenv('USD_CNY_ERROR_LOG=' . $log);
$argv[1] = '--cron';
require $release . '/usd-cny.php';
