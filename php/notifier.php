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

function format_line($label, $price, $last_price)
{
    if ($last_price == 0 || $price == $last_price) {
        return $label . "\n" . number_format($price) . "\n\n";
    }

    $diff = $price - $last_price;
    $emoji = $diff > 0 ? '📈' : '📉';
    $sign = $diff > 0 ? '+' : '';

    return $label . "\n" . number_format($last_price) . " ➜ " . number_format($price) . "\n"
        . $emoji . ' ' . $sign . number_format($diff) . "\n\n";
}

function send_price_update($gold, $silver, $last_gold, $last_silver)
{
    $text = "📊 بروزرسانی بازار\n\n";
    $text .= format_line('🥇 طلا', $gold, $last_gold);
    $text .= format_line('🥈 نقره', $silver, $last_silver);

    broadcast(trim($text));
}

function send_monthly_average($month_label, $avg_gold, $avg_silver)
{
    $text = "📅 میانگین قیمت ماه $month_label\n\n"
        . '🥇 طلا: ' . number_format($avg_gold) . "\n"
        . '🥈 نقره: ' . number_format($avg_silver);

    broadcast($text);
}

function send_weekly_summary($gold_high, $gold_low, $silver_high, $silver_low)
{
    $text = "🗓️ خلاصه هفتگی\n\n"
        . "🥇 بالاترین قیمت طلا\n" . number_format($gold_high) . "\n"
        . "🥇 پایین‌ترین قیمت طلا\n" . number_format($gold_low) . "\n\n"
        . "🥈 بالاترین قیمت نقره\n" . number_format($silver_high) . "\n"
        . "🥈 پایین‌ترین قیمت نقره\n" . number_format($silver_low);

    broadcast($text);
}
