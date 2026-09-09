<?php
// نقطه‌ی ورود ربات: قیمت رو می‌گیره، تو تاریخچه ثبت می‌کنه، در صورت لزوم میانگین
// ماه قبل / خلاصه هفتگی رو می‌فرسته و در نهایت پیام بروزرسانی قیمت لحظه‌ای رو به تلگرام ارسال می‌کنه.
// این فایل قراره هر چند دقیقه یک‌بار توسط Cron Job اجرا بشه.

require __DIR__ . '/config.php';
require __DIR__ . '/jalali.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/storage.php';
require __DIR__ . '/notifier.php';
require __DIR__ . '/diagnostics.php';

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

// شنبه تا چهارشنبه بازار بازه؛ پنجشنبه(۴) و جمعه(۵) تعطیله.
// روی «اولین اجرای بعد از ساعت بازگشایی» کار می‌کنه، نه دقیقاً سر همون دقیقه — قبلاً اگه
// کرون سر اون دقیقه اجرا نمی‌شد (مثل کرون ساعتی فعلی) این پیام هیچ‌وقت نمی‌رفت
function check_market_open($now)
{
    $weekday = (int) $now->format('N');

    if ($weekday === 4 || $weekday === 5) {
        return;
    }

    if ($now->format('H:i') < MARKET_OPEN_TIME) {
        return;
    }

    $today = $now->format('Y-m-d');

    if (load_last_market_open() === $today) {
        return;
    }

    send_market_open();
    save_last_market_open($today);
}

function in_quiet_window($now)
{
    $time = $now->format('H:i');

    return $time >= QUIET_HOURS_START && $time < QUIET_HOURS_END;
}

// بین پایان سکوت (۰۷:۰۳) و باز شدن بازار (۱۱:۰۳)، فقط طلا/انس/نقره/حباب ارسال می‌شه، بدون ارزها
function is_currency_muted($now)
{
    $time = $now->format('H:i');

    return $time >= QUIET_HOURS_END && $time < MARKET_OPEN_TIME;
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
    // موقتی: یک بار وضعیت محیط اجرا رو به چت خصوصی می‌فرسته و بعدش دیگه هیچ‌وقت تکرار نمی‌شه
    send_diagnostics_once();

    $now = tehran_now();

    $prices = [
        'gold' => get_gold_price(),
        'silver' => get_silver_price(),
    ] + get_tgju_prices();

    // دیتا همیشه (حتی توی ساعت سکوت) ثبت می‌شه تا میانگین ماهانه/خلاصه هفتگی درست باقی بمونه
    append_data($prices['gold'], $prices['silver'], $prices['usd'], $prices['ounce'], $prices['cny'], $prices['aed'], $prices['eur'], $prices['try']);

    // «روزی» که پیام به اسمش نوشته می‌شه؛ توی بازه‌ی سکوت هنوز روز قبل حساب می‌شه
    $day = morning_key_date($now);

    // اولین اجرای بعد از شروع سکوت، پیام آخر شبه (جمعه‌ها: خلاصه‌ی هفتگی)؛ باقی شب ساکته
    $is_night_close = in_quiet_window($now) && load_last_night() !== $day;

    if (in_quiet_window($now) && !$is_night_close) {
        return;
    }

    check_monthly_average();
    check_market_open($now);

    $last = load_prices();
    $cheapest = get_tablo_cheapest_platform();
    // نرخ مرجع tablo.gold برای خط «🥇Gold»؛ اگه در دسترس نبود قیمت milli.gold جاش می‌شینه.
    // هر دو به مقیاس نمایشی ربات (تومانِ هر «میلی») تبدیل شدن، پس قطع شدن API فقط منبع رو
    // عوض می‌کنه، نه بزرگیِ عدد رو
    $prices['gold_ref'] = get_tablo_reference_price() ?? toman($prices['gold']);
    $include_currencies = !is_currency_muted($now);

    if ($is_night_close) {
        // اگه روزی که داره تموم می‌شه جمعه‌ست، پیام آخر شب جاش رو به خلاصه‌ی هفتگی می‌ده
        $ending_weekday = (int) (new DateTime($day, new DateTimeZone(TEHRAN_TZ_NAME)))->format('N');

        if ($ending_weekday !== 5 || !send_friday_weekly_summary($day)) {
            send_last_update($prices, $last, $now->format('H:i'), $include_currencies, $cheapest);
        }

        save_last_night($day);
    } elseif (load_last_morning() !== $day) {
        send_morning_summary($prices, $last, $include_currencies, $cheapest);
        save_last_morning($day);
    } else {
        send_price_update($prices, $last, $include_currencies, $cheapest);
    }

    if (!save_prices($prices['gold'], $prices['gold_ref'], $prices['silver'], $prices['usd'], $prices['ounce'], $prices['cny'], $prices['aed'], $prices['eur'], $prices['try'])) {
        error_log('save_prices failed: ' . var_export(error_get_last(), true));
    }
}

try {
    main();
} catch (Throwable $e) {
    error_log('Fatal: ' . $e->getMessage());
}
