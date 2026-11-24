<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/jalali.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/notifier.php';

ini_set('error_log', getenv('USD_CNY_ERROR_LOG') ?: '');

function usd_cny_slot(DateTime $now): ?array
{
    $hour = (int) $now->format('G');
    if ($hour < 7) {
        return null;
    }

    $interval = 60;
    if ($hour === 11) {
        $interval = 15;
    } elseif ($hour >= 12 && $hour < 16) {
        $interval = 30;
    }
    $minute = intdiv((int) $now->format('i'), $interval) * $interval;
    $start = (clone $now)->setTime($hour, $minute);
    [$year, $month, $day] = to_jalali($now);

    return [
        'key' => sprintf('%04d-%02d-%02d-%02d-%02d', $year, $month, $day, $hour, $minute),
        'end' => $start->getTimestamp() + $interval * 60,
    ];
}

$delivery_lock = null;

try {
    $slot = usd_cny_slot(tehran_now());
    $requested_slot = $argv[1] ?? '';
    if ($requested_slot === '--slot') {
        echo $slot['key'] ?? '';
        exit;
    }

    $cron = $requested_slot === '--cron';
    if ($cron && $slot === null) {
        return;
    }

    if ($slot === null || (!$cron && $slot['key'] !== $requested_slot)) {
        throw new RuntimeException('The requested USD/CNY delivery slot is no longer active.');
    }

    $chat_id = getenv('USD_CNY_CHAT_ID') ?: '';
    $extra_chat_id = $cron ? (getenv('USD_CNY_EXTRA_CHAT_ID') ?: '') : '';
    $chat_ids = [$chat_id];
    if ($extra_chat_id !== '' && $extra_chat_id !== $chat_id) {
        $chat_ids[] = $extra_chat_id;
    }
    if (BOT_TOKEN === '' || preg_grep('/\A[1-9][0-9]*\z/', $chat_ids, PREG_GREP_INVERT)) {
        throw new RuntimeException('Configure BOT_TOKEN and valid private recipient IDs.');
    }

    $delivered = [];
    if ($cron) {
        $state_file = getenv('USD_CNY_STATE_FILE') ?: __DIR__ . '/.usd-cny-last-slot';
        $delivery_lock = fopen($state_file . '.lock', 'c');
        if ($delivery_lock === false || !flock($delivery_lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock the exchange rate delivery state.');
        }

        if (is_file($state_file)) {
            $previous = file_get_contents($state_file);
            if ($previous === false) {
                throw new RuntimeException('Could not read the exchange rate delivery state.');
            }
            $record = trim($previous);
            if ($record === $slot['key']) {
                // Older cPanel runs recorded only the primary recipient's slot.
                $delivered = [$chat_id];
            } elseif ($record !== '' && !preg_match('/\A\d{4}(?:-\d{2}){4}\z/', $record)) {
                $history = json_decode($record, true, 512, JSON_THROW_ON_ERROR);
                if (($history['slot'] ?? null) === $slot['key']) {
                    $delivered = $history['recipients'];
                }
            }
        }

        $chat_ids = array_diff($chat_ids, $delivered);
        if (!$chat_ids || time() >= $slot['end']) {
            return;
        }
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

    $failed = false;
    foreach ($chat_ids as $recipient) {
        if (!telegram_send_message($recipient, $message, $slot['end'])) {
            $failed = true;
            continue;
        }

        if ($cron) {
            $delivered[] = $recipient;
            $record = json_encode(['slot' => $slot['key'], 'recipients' => $delivered], JSON_THROW_ON_ERROR) . PHP_EOL;
            $pending_state = $state_file . '.tmp';
            if (file_put_contents($pending_state, $record) !== strlen($record) || !rename($pending_state, $state_file)) {
                throw new RuntimeException('Telegram accepted the message, but the delivery slot could not be saved.');
            }
        }
    }

    if ($failed) {
        throw new RuntimeException('Telegram did not confirm every private delivery.');
    }
    echo 'Telegram confirmed the exchange rate message.', PHP_EOL;
} catch (Throwable $error) {
    error_log($error->getMessage());
    exit(1);
} finally {
    if (is_resource($delivery_lock)) {
        fclose($delivery_lock);
    }
}
