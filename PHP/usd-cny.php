<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/jalali.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/notifier.php';

ini_set('error_log', '');

function usd_cny_slot(DateTime $now): ?array
{
    $hour = (int) $now->format('G');
    if ($hour < 7) {
        return null;
    }

    $interval = match (true) {
        $hour === 11 => 15,
        $hour >= 12 && $hour < 16 => 30,
        default => 60,
    };
    $minute = intdiv((int) $now->format('i'), $interval) * $interval;
    $start = (clone $now)->setTime($hour, $minute);
    [$year, $month, $day] = to_jalali($now);

    return [
        'key' => sprintf('%04d-%02d-%02d-%02d-%02d', $year, $month, $day, $hour, $minute),
        'end' => $start->getTimestamp() + $interval * 60,
    ];
}

try {
    $slot = usd_cny_slot(tehran_now());
    $requested_slot = $argv[1] ?? '';
    if ($requested_slot === '--slot') {
        echo $slot['key'] ?? '';
        exit;
    }

    if ($slot === null || $slot['key'] !== $requested_slot) {
        throw new RuntimeException('The requested USD/CNY delivery slot is no longer active.');
    }

    $chat_id = getenv('USD_CNY_CHAT_ID') ?: '';
    if (BOT_TOKEN === '' || !preg_match('/\A[1-9][0-9]*\z/', $chat_id)) {
        throw new RuntimeException('Configure BOT_TOKEN and a private USD_CNY_CHAT_ID.');
    }

    $prices = http_get_json(TGJU_URL);
    // هر دو قیمت به ریال هستند و نسبتشان تعداد یوان برای یک دلار است.
    $usd = (float) extract_tgju_price($prices, 'price_dollar_rl', TGJU_URL);
    $cny = (float) extract_tgju_price($prices, 'price_cny', TGJU_URL);
    if (!is_finite($usd) || !is_finite($cny) || $usd <= 0 || $cny <= 0) {
        throw new RuntimeException('TGJU returned an invalid dollar or yuan price.');
    }

    $message = '$1 = 🇨🇳 CNY ' . number_format($usd / $cny, 2, ',', '');

    if (!telegram_send_message($chat_id, $message, $slot['end'])) {
        throw new RuntimeException('Telegram did not confirm the USD/CNY message.');
    }

    echo 'Telegram confirmed the USD/CNY message.', PHP_EOL;
} catch (Throwable $error) {
    error_log($error->getMessage());
    exit(1);
}
