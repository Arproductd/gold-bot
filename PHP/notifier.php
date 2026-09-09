<?php
// ارسال پیام به تلگرام: قالب‌بندی متن پیام‌های قیمت، میانگین ماهانه و خلاصه هفتگی.

function telegram_send_message($chat_id, $text)
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $body = http_build_query([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]);

    $last_error = '';

    for ($attempt = 1; $attempt <= HTTP_MAX_ATTEMPTS; $attempt++) {
        if ($attempt > 1) {
            sleep(HTTP_RETRY_DELAY);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, HTTP_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response !== false && $status >= 200 && $status < 300) {
            if ($attempt > 1) {
                error_log("ارسال پیام تلگرام به $chat_id در تلاش $attempt موفق شد");
            }

            return;
        }

        // پیام رد شده (آیدی اشتباه، HTML خراب) با تکرار درست نمی‌شه
        if ($response !== false && $status >= 400 && $status < 500) {
            error_log("ارسال پیام تلگرام به $chat_id رد شد (HTTP $status): $response");

            return;
        }

        $last_error = $response === false ? "curl: $error" : "HTTP $status";
    }

    error_log("ارسال پیام تلگرام به $chat_id بعد از " . HTTP_MAX_ATTEMPTS . " تلاش ناموفق بود — $last_error");
}

function broadcast($text)
{
    foreach ($GLOBALS['CHAT_IDS'] as $chat_id) {
        telegram_send_message($chat_id, $text);
    }
}

// usd/eur/aed/cny/try (tgju) به ریال‌ان، برای نمایش تبدیل به تومان می‌شن (÷۱۰).
// gold (milli.gold) از قبل تومانِ هر سوته و silver/ounce هم تومان و دلارن — دست‌نخورده می‌مونن.
function toman($rial)
{
    return $rial / 10;
}

const SEPARATOR = "\n—————\n";

function format_line($label, $price, $last_price, $decimals = 0, $prefix = '')
{
    if ($last_price == 0 || $price == $last_price) {
        return $label . ': ' . $prefix . number_format($price, $decimals);
    }

    $diff = $price - $last_price;
    $sign = $diff > 0 ? '+' : ($diff < 0 ? '-' : '');

    return $label . ': <s>' . $prefix . number_format($last_price, $decimals) . '</s> ➜ ' . $prefix . number_format($price, $decimals)
        . ' | ' . $sign . $prefix . number_format(abs($diff), $decimals);
}

// $cheapest از get_tablo_cheapest_platform() میاد؛ ممکنه null باشه (API در دسترس نبود)، اون‌وقت این خط اصلاً اضافه نمی‌شه
function format_tablo_low_line($cheapest)
{
    if ($cheapest === null) {
        return null;
    }

    return '🥇Gold-Low-' . ucfirst($cheapest['platform']) . ': ' . number_format($cheapest['price']);
}

function build_asset_lines($prices, $last, $cheapest = null)
{
    $lines = [
        format_line('🥇Gold', $prices['gold_ref'], $last['gold_ref'] ?? 0),
        format_line('🥈Silver', $prices['silver'], $last['silver'] ?? 0),
        format_line('🥇G-Ounce', $prices['ounce'], $last['ounce'] ?? 0, 2, '$'),
    ];

    $low_line = format_tablo_low_line($cheapest);

    if ($low_line !== null) {
        $lines[] = $low_line;
    }

    return $lines;
}

function build_currency_lines($prices, $last)
{
    return [
        format_line('🇺🇸 Dollar', toman($prices['usd']), toman($last['usd'] ?? 0)),
        format_line('🇪🇺 EUR', toman($prices['eur']), toman($last['eur'] ?? 0)),
        format_line('🇦🇪 AED', toman($prices['aed']), toman($last['aed'] ?? 0)),
        format_line('🇨🇳 CNY', toman($prices['cny']), toman($last['cny'] ?? 0)),
        format_line('🇹🇷 TRY', toman($prices['try']), toman($last['try'] ?? 0)),
    ];
}

function build_market_lines($prices, $last, $include_currencies = true, $cheapest = null)
{
    $lines = build_asset_lines($prices, $last, $cheapest);

    if ($include_currencies) {
        array_splice($lines, 1, 0, build_currency_lines($prices, $last));
    }

    return $lines;
}

function send_price_update($prices, $last, $include_currencies = true, $cheapest = null)
{
    $text = implode(SEPARATOR, build_market_lines($prices, $last, $include_currencies, $cheapest));

    broadcast(trim($text));
}

function send_last_update($prices, $last, $time_label, $include_currencies = true, $cheapest = null)
{
    $text = "Now: $time_label | This is the latest update 🥱\n\n" . implode(SEPARATOR, build_market_lines($prices, $last, $include_currencies, $cheapest));

    broadcast(trim($text));
}

function send_morning_summary($prices, $last, $include_currencies = true, $cheapest = null)
{
    $text = "😴 Overnight Summary\n\n" . implode(SEPARATOR, build_market_lines($prices, $last, $include_currencies, $cheapest))
        . "\n\nLet's see what's up today...";

    broadcast(trim($text));
}

function send_market_open()
{
    broadcast('بازار باز شد...');
}

function send_monthly_average($month_label, $averages)
{
    $lines = [
        format_line('🥇Gold', $averages['gold'], 0),
        format_line('🇺🇸 Dollar', toman($averages['usd']), 0),
        format_line('🇪🇺 EUR', toman($averages['eur']), 0),
        format_line('🇦🇪 AED', toman($averages['aed']), 0),
        format_line('🇨🇳 CNY', toman($averages['cny']), 0),
        format_line('🇹🇷 TRY', toman($averages['try']), 0),
        format_line('🥈Silver', $averages['silver'], 0),
        format_line('🥇G-Ounce', $averages['ounce'], 0, 2, '$'),
    ];

    $text = "📅 Monthly Average — $month_label\n\n" . implode(SEPARATOR, $lines);

    broadcast($text);
}

function send_weekly_summary($summary)
{
    $lines = [
        format_line('🥇Gold High', $summary['gold_high'], 0),
        format_line('🥇Gold Low', $summary['gold_low'], 0),
        format_line('🇺🇸 Dollar High', toman($summary['usd_high']), 0),
        format_line('🇺🇸 Dollar Low', toman($summary['usd_low']), 0),
        format_line('🇪🇺 EUR High', toman($summary['eur_high']), 0),
        format_line('🇪🇺 EUR Low', toman($summary['eur_low']), 0),
        format_line('🇦🇪 AED High', toman($summary['aed_high']), 0),
        format_line('🇦🇪 AED Low', toman($summary['aed_low']), 0),
        format_line('🇨🇳 CNY High', toman($summary['cny_high']), 0),
        format_line('🇨🇳 CNY Low', toman($summary['cny_low']), 0),
        format_line('🇹🇷 TRY High', toman($summary['try_high']), 0),
        format_line('🇹🇷 TRY Low', toman($summary['try_low']), 0),
        format_line('🥇G-Ounce High', $summary['ounce_high'], 0, 2, '$'),
        format_line('🥇G-Ounce Low', $summary['ounce_low'], 0, 2, '$'),
        format_line('🥈Silver High', $summary['silver_high'], 0),
        format_line('🥈Silver Low', $summary['silver_low'], 0),
    ];

    $text = "🗓️ Weekly Summary\n\n" . implode(SEPARATOR, $lines);

    broadcast($text);
}
