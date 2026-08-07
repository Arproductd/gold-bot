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

// API‌ها همه‌چیز رو به ریال می‌دن؛ برای نمایش به تومان تبدیل می‌کنیم (÷۱۰). انس جهانی دلاریه، دست‌نخورده می‌مونه.
function toman($rial)
{
    return $rial / 10;
}

function format_line($label, $price, $last_price, $decimals = 0, $prefix = '')
{
    if ($last_price == 0 || $price == $last_price) {
        return $label . "\n" . $prefix . number_format($price, $decimals) . "\n\n";
    }

    $diff = $price - $last_price;
    $emoji = $diff > 0 ? '📈' : '📉';
    $sign = $diff > 0 ? '+' : '';

    return $label . "\n" . $prefix . number_format($last_price, $decimals) . " ➜ " . $prefix . number_format($price, $decimals) . "\n"
        . $emoji . ' ' . $sign . $prefix . number_format($diff, $decimals) . "\n\n";
}

function format_bubble_line($bubble)
{
    // حباب مثبت یعنی طلا نسبت به قیمت ذاتی‌ش گرون‌تره (احتیاط)، منفی یعنی ارزون‌تره (فرصت خرید)
    $is_cheap = $bubble['percent'] < 0;
    $emoji = $is_cheap ? '🟢' : '🔴';
    $suggestion = $is_cheap ? 'پیشنهاد خرید' : 'پیشنهاد صبر';
    $sign = $bubble['percent'] >= 0 ? '+' : '';

    // amount توی prices.php به ازای هر «میلی» و به ریاله؛ برای این خط به ازای هر گرم و به تومان نشون می‌دیم (×۱۰۰)
    $amount_per_gram_toman = abs($bubble['amount']) * 100;

    return "$emoji حباب طلای ۱۸ عیار ($suggestion)\n"
        . '(' . $sign . number_format($bubble['percent'], 1) . "%)\n"
        . number_format($amount_per_gram_toman) . " تومان\n\n";
}

function send_price_update($gold, $silver, $usd, $ounce, $last_gold, $last_silver, $last_usd, $last_ounce, $bubble)
{
    $text = "📊 بروزرسانی بازار\n\n";
    $text .= format_line('🥇 طلا (تومان)', toman($gold), toman($last_gold));
    $text .= format_line('🥈 نقره (تومان)', toman($silver), toman($last_silver));
    $text .= format_line('💵 دلار (تومان)', toman($usd), toman($last_usd));
    $text .= format_line('🌍 انس جهانی طلا', $ounce, $last_ounce, 2, '$');
    $text .= format_bubble_line($bubble);

    broadcast(trim($text));
}

function send_morning_summary($gold, $silver, $usd, $ounce, $last_gold, $last_silver, $last_usd, $last_ounce, $bubble)
{
    $text = "😴 خلاصه‌ی این تایمی که خواب بودید:\n\n";
    $text .= format_line('🥇 طلا (تومان)', toman($gold), toman($last_gold));
    $text .= format_line('🥈 نقره (تومان)', toman($silver), toman($last_silver));
    $text .= format_line('💵 دلار (تومان)', toman($usd), toman($last_usd));
    $text .= format_line('🌍 انس جهانی طلا', $ounce, $last_ounce, 2, '$');
    $text .= format_bubble_line($bubble);
    $text .= "بریم که ببینیم امروز چه خبره...";

    broadcast(trim($text));
}

function send_monthly_average($month_label, $averages)
{
    $text = "📅 میانگین قیمت ماه $month_label\n\n"
        . '🥇 طلا: ' . number_format(toman($averages['gold'])) . " تومان\n"
        . '🥈 نقره: ' . number_format(toman($averages['silver'])) . " تومان\n"
        . '💵 دلار: ' . number_format(toman($averages['usd'])) . " تومان\n"
        . '🌍 انس جهانی طلا: $' . number_format($averages['ounce'], 2);

    broadcast($text);
}

function send_weekly_summary($summary)
{
    $text = "🗓️ خلاصه هفتگی\n\n"
        . "🥇 بالاترین قیمت طلا\n" . number_format(toman($summary['gold_high'])) . " تومان\n"
        . "🥇 پایین‌ترین قیمت طلا\n" . number_format(toman($summary['gold_low'])) . " تومان\n\n"
        . "🥈 بالاترین قیمت نقره\n" . number_format(toman($summary['silver_high'])) . " تومان\n"
        . "🥈 پایین‌ترین قیمت نقره\n" . number_format(toman($summary['silver_low'])) . " تومان\n\n"
        . "💵 بالاترین قیمت دلار\n" . number_format(toman($summary['usd_high'])) . " تومان\n"
        . "💵 پایین‌ترین قیمت دلار\n" . number_format(toman($summary['usd_low'])) . " تومان\n\n"
        . "🌍 بالاترین قیمت انس جهانی طلا\n$" . number_format($summary['ounce_high'], 2) . "\n"
        . "🌍 پایین‌ترین قیمت انس جهانی طلا\n$" . number_format($summary['ounce_low'], 2);

    broadcast($text);
}
