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

    $snapshot = http_get_json(TGJU_URL);
    $prices = [];
    foreach (['usd' => 'price_dollar_rl', 'aed' => 'price_aed', 'cny' => 'price_cny'] as $currency => $field) {
        $price = (float) extract_tgju_price($snapshot, $field, TGJU_URL);
        if (!is_finite($price) || $price <= 0) {
            throw new RuntimeException("TGJU returned an invalid $currency price.");
        }
        $prices[$currency] = $price;
    }

    // قیمت‌ها به ریال‌اند. تقسیم قیمت ارز مبدأ بر ارز مقصد، نرخ تبدیل را می‌دهد.
    $message = implode("\n", [
        '$1 = 🇨🇳 CNY ' . number_format($prices['usd'] / $prices['cny'], 2, ',', ''),
        '1 AED = 🇨🇳 CNY ' . number_format($prices['aed'] / $prices['cny'], 2, ',', ''),
        '$1 = 🇦🇪 AED ' . number_format($prices['usd'] / $prices['aed'], 2, ',', ''),
    ]);

    if (!telegram_send_message($chat_id, $message, $slot['end'])) {
        throw new RuntimeException('Telegram did not confirm the exchange rate message.');
    }

    echo 'Telegram confirmed the exchange rate message.', PHP_EOL;
} catch (Throwable $error) {
    error_log($error->getMessage());
    exit(1);
}
