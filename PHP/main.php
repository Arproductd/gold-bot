<?php
// نقطه‌ی ورود ربات: قیمت رو می‌گیره، تو تاریخچه ثبت می‌کنه، در صورت لزوم میانگین
// ماه قبل / خلاصه هفتگی رو می‌فرسته و در نهایت پیام بروزرسانی قیمت لحظه‌ای رو به تلگرام ارسال می‌کنه.
// این فایل قراره هر چند دقیقه یک‌بار توسط Cron Job اجرا بشه.

require __DIR__ . '/config.php';
require __DIR__ . '/jalali.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/storage.php';
require __DIR__ . '/notifier.php';

function check_monthly_average()
{
    $now = tehran_now();
    [$year, $month] = previous_jalali_month($now);
    $key = sprintf('%d-%02d', $year, $month);

    if (load_last_average_month() === $key) {
        return;
    }

    $averages = month_average($year, $month);

    if ($averages !== null) {
        send_monthly_average(month_label($year, $month), $averages);
    }

    save_last_average_month($key);
}

// $day: تاریخ (Y-m-d) جمعه‌ای که داره تموم می‌شه؛ هفته از شنبه تا همون جمعه حساب می‌شه.
// خروجی می‌گه که خلاصه واقعاً ارسال شد یا نه (برای جلوگیری از جایگزین نشدن پیام آخر شب).
function send_friday_weekly_summary($day)
{
    if (load_last_weekly() === $day) {
        return false;
    }

    $week_start = (new DateTime($day, new DateTimeZone(TEHRAN_TZ_NAME)))->modify('-6 days')->format('Y-m-d');
    $summary = week_high_low($week_start, $day);

    if ($summary !== null) {
        send_weekly_summary($summary);
    }

    save_last_weekly($day);

    return $summary !== null;
}

// شنبه تا چهارشنبه بازار بازه؛ پنجشنبه(۴) و جمعه(۵) تعطیله
function check_market_open($now)
{
    $weekday = (int) $now->format('N');

    if ($weekday === 4 || $weekday === 5) {
        return;
    }

    if ($now->format('H:i') !== MARKET_OPEN_TIME) {
        return;
    }

    $today = $now->format('Y-m-d');

    if (load_last_market_open() === $today) {
        return;
    }

    send_market_open();
    save_last_market_open($today);
}

function is_quiet_hours($now)
{
    $time = $now->format('H:i');

    return $time > QUIET_HOURS_START && $time < QUIET_HOURS_END;
}

// روزی که برای تشخیص «اولین پیام بعد از سکوت» استفاده می‌شه؛ چون سکوت از ۰۰:۰۳ (بعد از عوض شدن
// تاریخ) تا ۰۷:۰۳ ادامه داره، تا قبل از پایان سکوت هنوز «دیروز» حساب می‌شه، وگرنه دقیقه‌ی ۰۰:۰۳
// (اولین اجرای غیرساکت روز جدید) به‌جای پیام مخصوص خودش، خلاصه‌ی صبحگاهی رو می‌گرفت
function morning_key_date($now)
{
    if ($now->format('H:i') < QUIET_HOURS_END) {
        return (clone $now)->modify('-1 day')->format('Y-m-d');
    }

    return $now->format('Y-m-d');
}

function main()
{
    $now = tehran_now();

    $prices = [
        'gold' => get_gold_price(),
        'silver' => get_silver_price(),
    ] + get_tgju_prices();

    // دیتا همیشه (حتی توی ساعت سکوت) ثبت می‌شه تا میانگین ماهانه/خلاصه هفتگی درست باقی بمونه
    append_data($prices['gold'], $prices['silver'], $prices['usd'], $prices['ounce'], $prices['cny'], $prices['aed'], $prices['eur'], $prices['try']);

    // بین ۰۰:۰۳ و ۰۷:۰۳ پیامی ارسال نمی‌شه؛ ۰۰:۰۳ خودش آخرین پیام شبه
    if (is_quiet_hours($now)) {
        return;
    }

    check_monthly_average();
    check_market_open($now);

    $last = load_prices();
    $bubble = calculate_gold_bubble($prices['gold'], $prices['usd'], $prices['ounce']);

    $today = morning_key_date($now);
    if (load_last_morning() !== $today) {
        send_morning_summary($prices, $last, $bubble);
        save_last_morning($today);
    } elseif ($now->format('H:i') === QUIET_HOURS_START) {
        // اگه روزی که داره تموم می‌شه جمعه‌ست، پیام آخر شب جاش رو به خلاصه‌ی هفتگی می‌ده
        $ending_weekday = (int) (new DateTime($today, new DateTimeZone(TEHRAN_TZ_NAME)))->format('N');

        if ($ending_weekday !== 5 || !send_friday_weekly_summary($today)) {
            send_last_update($prices, $last, $bubble, $now->format('H:i'));
        }
    } else {
        send_price_update($prices, $last, $bubble);
    }

    if (!save_prices($prices['gold'], $prices['silver'], $prices['usd'], $prices['ounce'], $prices['cny'], $prices['aed'], $prices['eur'], $prices['try'])) {
        error_log('save_prices failed: ' . var_export(error_get_last(), true));
    }
}

try {
    main();
} catch (Throwable $e) {
    error_log('Fatal: ' . $e->getMessage());
}
