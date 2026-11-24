<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/notifier.php';

ini_set('error_log', '');

try {
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

    $rate = number_format($usd / $cny, 2, '.', '');
    $rate = strtr($rate, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫',
    ]);
    $message = "💱 هر ۱ دلار آمریکا ≈ $rate یوان چین\nبر اساس نرخ بازار ایران (TGJU)";

    if (!telegram_send_message($chat_id, $message)) {
        throw new RuntimeException('Telegram did not confirm the hourly USD/CNY message.');
    }

    echo 'Telegram confirmed the hourly USD/CNY message.', PHP_EOL;
} catch (Throwable $error) {
    error_log($error->getMessage());
    exit(1);
}
