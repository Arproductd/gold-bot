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

function format_line($label, $price, $last_price, $decimals = 0)
{
    if ($last_price == 0 || $price == $last_price) {
        return $label . "\n" . number_format($price, $decimals) . "\n\n";
    }

    $diff = $price - $last_price;
    $emoji = $diff > 0 ? '📈' : '📉';
    $sign = $diff > 0 ? '+' : '';

    return $label . "\n" . number_format($last_price, $decimals) . " ➜ " . number_format($price, $decimals) . "\n"
        . $emoji . ' ' . $sign . number_format($diff, $decimals) . "\n\n";
}

function format_bubble_line($bubble)
{
    // حباب مثبت یعنی طلا نسبت به قیمت ذاتی‌ش گرون‌تره (احتیاط)، منفی یعنی ارزون‌تره (فرصت خرید)
    $emoji = $bubble['percent'] >= 0 ? '🔴' : '🟢';
    $sign = $bubble['percent'] >= 0 ? '+' : '';

    return "$emoji حباب طلای ۱۸ عیار\n"
        . number_format($bubble['amount']) . ' ریال (' . $sign . number_format($bubble['percent'], 1) . "%)\n\n";
}

function send_price_update($gold, $silver, $usd, $ounce, $last_gold, $last_silver, $last_usd, $last_ounce, $bubble)
{
    $text = "📊 بروزرسانی بازار\n\n";
    $text .= format_line('🥇 طلا', $gold, $last_gold);
    $text .= format_line('🥈 نقره', $silver, $last_silver);
    $text .= format_line('💵 دلار', $usd, $last_usd);
    $text .= format_line('🌍 انس جهانی طلا', $ounce, $last_ounce, 2);
    $text .= format_bubble_line($bubble);

    broadcast(trim($text));
}

function send_morning_summary($gold, $silver, $usd, $ounce, $last_gold, $last_silver, $last_usd, $last_ounce, $bubble)
{
    $text = "😴 خلاصه‌ی این تایمی که خواب بودید:\n\n";
    $text .= format_line('🥇 طلا', $gold, $last_gold);
    $text .= format_line('🥈 نقره', $silver, $last_silver);
    $text .= format_line('💵 دلار', $usd, $last_usd);
    $text .= format_line('🌍 انس جهانی طلا', $ounce, $last_ounce, 2);
    $text .= format_bubble_line($bubble);
    $text .= "بریم که ببینیم امروز چه خبره...";

    broadcast(trim($text));
}

function send_monthly_average($month_label, $averages)
{
    $text = "📅 میانگین قیمت ماه $month_label\n\n"
        . '🥇 طلا: ' . number_format($averages['gold']) . "\n"
        . '🥈 نقره: ' . number_format($averages['silver']) . "\n"
        . '💵 دلار: ' . number_format($averages['usd']) . "\n"
        . '🌍 انس جهانی طلا: ' . number_format($averages['ounce'], 2);

    broadcast($text);
}

function send_weekly_summary($summary)
{
    $text = "🗓️ خلاصه هفتگی\n\n"
        . "🥇 بالاترین قیمت طلا\n" . number_format($summary['gold_high']) . "\n"
        . "🥇 پایین‌ترین قیمت طلا\n" . number_format($summary['gold_low']) . "\n\n"
        . "🥈 بالاترین قیمت نقره\n" . number_format($summary['silver_high']) . "\n"
        . "🥈 پایین‌ترین قیمت نقره\n" . number_format($summary['silver_low']) . "\n\n"
        . "💵 بالاترین قیمت دلار\n" . number_format($summary['usd_high']) . "\n"
        . "💵 پایین‌ترین قیمت دلار\n" . number_format($summary['usd_low']) . "\n\n"
        . "🌍 بالاترین قیمت انس جهانی طلا\n" . number_format($summary['ounce_high'], 2) . "\n"
        . "🌍 پایین‌ترین قیمت انس جهانی طلا\n" . number_format($summary['ounce_low'], 2);

    broadcast($text);
}
