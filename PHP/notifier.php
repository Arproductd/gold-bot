<?php
// ارسال پیام به تلگرام: قالب‌بندی متن پیام‌های قیمت، میانگین ماهانه و خلاصه هفتگی.

function telegram_send_message($chat_id, $text)
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("ارسال پیام تلگرام به $chat_id ناموفق بود (curl): $curl_error");
    } elseif ($status < 200 || $status >= 300) {
        error_log("ارسال پیام تلگرام به $chat_id ناموفق بود (HTTP $status): $response");
    }
}

function broadcast($text)
{
    foreach ($GLOBALS['CHAT_IDS'] as $chat_id) {
        telegram_send_message($chat_id, $text);
    }
}

// gold(milli.gold) و usd/eur/aed/cny(tgju) به ریال‌ان، برای نمایش تبدیل به تومان می‌شن (÷۱۰).
// silver(melligold) و ounce از قبل به ترتیب تومان و دلارن، دست‌نخورده می‌مونن.
function toman($rial)
{
    return $rial / 10;
}

// milli.gold قیمت رو به ازای هر «میلی» (یک‌هزارم گرم) و به ریال می‌ده. نرخ مرجع و قیمت پلتفرم‌های
// tablo.gold تومانِ هر «گرم»‌ان، پس هر جا این دو کنار هم نشون داده می‌شن باید هم‌واحد بشن.
function gold_gram_toman($rial_per_milli)
{
    return toman($rial_per_milli) * 1000;
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

function format_bubble_line($bubble)
{
    $percent = $bubble['percent'];
    $sign = $percent >= 0 ? '+' : '';

    // amount توی prices.php به ازای هر «میلی» و به ریاله؛ برای این خط به ازای هر گرم و به تومان نشون می‌دیم (×۱۰۰)
    $amount_per_gram_toman = abs($bubble['amount']) * 100;

    return '🥇Bubble: ' . number_format($amount_per_gram_toman) . ' | ' . $sign . number_format($percent, 1) . '%';
}

// $cheapest از get_tablo_cheapest_platform() میاد؛ ممکنه null باشه (API در دسترس نبود)، اون‌وقت این خط اصلاً اضافه نمی‌شه
function format_tablo_low_line($cheapest)
{
    if ($cheapest === null) {
        return null;
    }

    return '🥇Gold-Low-' . ucfirst($cheapest['platform']) . ': ' . number_format($cheapest['price']);
}

function build_asset_lines($prices, $last, $bubble, $cheapest = null)
{
    $lines = [
        format_line('🥇Gold', $prices['gold_ref'], $last['gold_ref'] ?? 0),
        format_line('🥈Silver', $prices['silver'], $last['silver'] ?? 0),
        format_line('🥇G-Ounce', $prices['ounce'], $last['ounce'] ?? 0, 2, '$'),
        format_bubble_line($bubble),
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

function build_market_lines($prices, $last, $bubble, $include_currencies = true, $cheapest = null)
{
    $lines = build_asset_lines($prices, $last, $bubble, $cheapest);

    if ($include_currencies) {
        array_splice($lines, 1, 0, build_currency_lines($prices, $last));
    }

    return $lines;
}

function send_price_update($prices, $last, $bubble, $include_currencies = true, $cheapest = null)
{
    $text = implode(SEPARATOR, build_market_lines($prices, $last, $bubble, $include_currencies, $cheapest));

    broadcast(trim($text));
}

function send_last_update($prices, $last, $bubble, $time_label, $include_currencies = true, $cheapest = null)
{
    $text = "Now: $time_label | This is the latest update 🥱\n\n" . implode(SEPARATOR, build_market_lines($prices, $last, $bubble, $include_currencies, $cheapest));

    broadcast(trim($text));
}

function send_morning_summary($prices, $last, $bubble, $include_currencies = true, $cheapest = null)
{
    $text = "😴 Overnight Summary\n\n" . implode(SEPARATOR, build_market_lines($prices, $last, $bubble, $include_currencies, $cheapest))
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
        format_line('🥇Gold', gold_gram_toman($averages['gold']), 0),
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
        format_line('🥇Gold High', gold_gram_toman($summary['gold_high']), 0),
        format_line('🥇Gold Low', gold_gram_toman($summary['gold_low']), 0),
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
